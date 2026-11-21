<?php
namespace App\Services;

use App\Models\{KalendiaAppointment, KalendiaAppointmentAction, KalendiaBooking, User};
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class KalendiaAppointmentService
{
    public function __construct(private KalendiaApi $api) {}
    public function record(array $data, bool $authoritative = false): ?KalendiaAppointment
    {
        if (! Schema::hasTable('kalendia_appointments') || ! preg_match('/^apt_[A-Za-z0-9_-]+$/D', (string) ($data['id'] ?? ''))) return null;
        $row = KalendiaAppointment::firstOrNew(['remote_id' => $data['id']]);
        $customer = $data['client_id'] ?? $row->remote_customer_id;
        if ($row->exists && $row->remote_customer_id && $customer !== $row->remote_customer_id) return null;
        $booking = KalendiaBooking::where('remote_id', $data['id'])->first();
        if ($booking && $booking->remote_customer_id !== $customer) return null;
        if ($booking && $row->user_id && (int) $row->user_id !== (int) $booking->user_id) return null;
        $userId = $booking?->user_id ?: $row->user_id;
        if (! $userId && is_string($customer)) {
            $known = KalendiaBooking::where('remote_customer_id', $customer)->distinct()->pluck('user_id');
            if ($known->count() > 1) return null;
            if ($known->count() === 1) $userId = $known->first();
        }
        if (! $userId && filled($data['customer_email'] ?? null)) {
            $users = User::whereRaw('LOWER(email) = ?', [mb_strtolower(trim($data['customer_email']))])->get();
            if ($users->count() === 1 && $users->first()->hasRole('Cliente')) $userId = $users->first()->id;
        }
        try {
            $start = isset($data['starts_at']) ? CarbonImmutable::parse($data['starts_at'], 'America/Bogota')->utc() : $row->starts_at;
            $end = isset($data['ends_at']) ? CarbonImmutable::parse($data['ends_at'], 'America/Bogota')->utc() : $row->ends_at;
            if (! $start || ! $end || $end->lte($start)) return null;
        } catch (\Throwable) { return null; }
        $values = ['user_id' => $userId, 'remote_customer_id' => $customer, 'starts_at' => $start, 'ends_at' => $end,
            'timezone' => 'America/Bogota', 'status' => Str::limit((string) ($data['status'] ?? $row->status ?? 'pending'), 60, ''), 'checked_at' => now()];
        foreach (['service_id' => 'remote_service_id', 'service_name' => 'service_name', 'staff_id' => 'staff_id', 'staff_name' => 'staff_name'] as $remote => $local) {
            if (is_string($data[$remote] ?? null)) $values[$local] = Str::limit($data[$remote], 255, '');
        }
        if (isset($data['customer_name']) || isset($data['customer_email'])) $values['customer'] = [
            'name' => $data['customer_name'] ?? null, 'email' => $data['customer_email'] ?? null];
        if (array_key_exists('virtual_meeting_url', $data)) {
            $link = $data['virtual_meeting_url'];
            $values['meeting_url'] = is_string($link) && filter_var($link, FILTER_VALIDATE_URL) && parse_url($link, PHP_URL_SCHEME) === 'https' ? $link : null;
        }
        $row->forceFill($values)->save();
        if ($authoritative) {
            $pending = KalendiaAppointmentAction::where('appointment_id',$row->id)->whereIn('status',['processing','uncertain'])->get();
            foreach ($pending as $action) {
                $body = $action->payload;
                $matches = $action->action === 'cancel' ? $row->status === 'cancelled'
                    : ($row->status !== 'cancelled' && isset($body['date'],$body['time'])
                        && $row->starts_at->equalTo(CarbonImmutable::parse($body['date'].' '.$body['time'],'America/Bogota')));
                if ($matches) $action->forceFill(['status'=>'succeeded','http_status'=>200,'lease'=>null,'leased_until'=>null])->save();
            }
        }
        if ($booking) $booking->forceFill(['remote_status' => $row->status, 'date' => $start->setTimezone('America/Bogota')->format('Y-m-d'),
            'time' => $start->setTimezone('America/Bogota')->format('H:i'), 'meeting_url' => $row->meeting_url])->save();
        return $row;
    }
    public function recordBooking(KalendiaBooking $booking): void
    {
        if (! $booking->remote_id || ! Schema::hasTable('kalendia_appointments')) return;
        $body = data_get($booking->request_payload, 'appointment', []);
        $service = $this->api->serviceById($booking->remote_service_id);
        $start = CarbonImmutable::parse($booking->date.' '.$booking->time, 'America/Bogota');
        $this->record(['id' => $booking->remote_id, 'client_id' => $booking->remote_customer_id,
            'service_id' => $booking->remote_service_id, 'service_name' => $service['name'] ?? $booking->purchase?->descripcion,
            'staff_id' => $body['staff_id'] ?? null, 'starts_at' => $start->toIso8601String(),
            'ends_at' => $start->addMinutes((int) ($service['duration_minutes'] ?? 60))->toIso8601String(),
            'status' => $booking->remote_status ?: 'pending', 'virtual_meeting_url' => $booking->meeting_url]);
    }
    /** One incremental page per run; COS reads only the local mirror. */
    public function syncPage(): int
    {
        if (! Schema::hasTable('kalendia_sync_states')) return 0;
        DB::table('kalendia_sync_states')->insertOrIgnore(['name' => 'appointments', 'page' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $state = DB::transaction(function () {
            $row = DB::table('kalendia_sync_states')->where('name', 'appointments')->lockForUpdate()->first();
            if ($row->leased_until && CarbonImmutable::parse($row->leased_until)->isFuture()) return null;
            $row->lease = (string) Str::uuid();
            $row->window_started_at ??= now()->toDateTimeString();
            DB::table('kalendia_sync_states')->where('id', $row->id)->update(['lease' => $row->lease,
                'leased_until' => now()->addMinute(), 'window_started_at' => $row->window_started_at]);
            return $row;
        });
        if (! $state) return 0;
        try {
            $query = ['page' => $state->page, 'per_page' => 100];
            if ($state->since) $query['updated_since'] = CarbonImmutable::parse($state->since)->utc()->toIso8601String();
            $result = $this->api->request('GET', 'appointments', $query);
            $items = $result['items'] ?? null;
            if (! is_array($items)) throw new KalendiaApiException();
            foreach ($items as $item) if (is_array($item)) $this->record($item, true);
            $finished = count($items) < 100;
            DB::table('kalendia_sync_states')->where('id', $state->id)->where('lease', $state->lease)->update([
                'page' => $finished ? 1 : $state->page + 1,
                'since' => $finished ? CarbonImmutable::parse($state->window_started_at)->subMinutes(2) : $state->since,
                'window_started_at' => $finished ? null : $state->window_started_at, 'lease' => null, 'leased_until' => null, 'updated_at' => now()]);
            return count($items);
        } catch (\Throwable $error) {
            DB::table('kalendia_sync_states')->where('id', $state->id)->where('lease', $state->lease)->update(['lease' => null, 'leased_until' => null]);
            throw $error;
        }
    }
    public function summary(User $user): array
    {
        $next = Schema::hasTable('kalendia_appointments') ? KalendiaAppointment::where('user_id', $user->id)
            ->whereNotIn('status', ['cancelled', 'completed', 'no_show'])->where('ends_at', '>', now()->utc())->orderBy('starts_at')->first() : null;
        return ['next' => $next, 'status' => $next
            ? ($next->status !== 'confirmed' ? 'Reunión pendiente de confirmación' : ($next->starts_at->isPast() ? 'Reunión en curso' : 'Reunión programada'))
            : 'Sin próximas reuniones'];
    }
    public function change(KalendiaAppointment $appointment, User $actor, string $uuid, string $action, array $input): void
    {
        abort_unless($actor->hasRole('Administrador') && ! $actor->hasRole('Cliente'), 403);
        $timezone = KalendiaTimezone::validate($input['timezone'] ?? null);
        $local = $action === 'reschedule' ? CarbonImmutable::createFromFormat('!Y-m-d H:i', $input['date'].' '.$input['time'], $timezone) : null;
        $canonical = $local?->setTimezone('America/Bogota');
        $payload = $action === 'cancel' ? ['status' => 'cancelled'] : ['date' => $canonical->format('Y-m-d'), 'time' => $canonical->format('H:i')];
        $operation = KalendiaAppointmentAction::where('uuid', $uuid)->first();
        $replaying = $operation && in_array($operation->status, ['uncertain', 'processing'], true);
        if (! $operation && $action === 'reschedule') {
            app(KalendiaRegistrationBookingService::class)->validateDate($input['date'], $timezone);
            app(KalendiaRegistrationBookingService::class)->validateDate($payload['date']);
            $slots = $this->api->request('GET', 'availability', ['service_id' => $appointment->remote_service_id,
                'staff_id' => $appointment->staff_id, 'date' => $payload['date'], 'timezone' => 'America/Bogota']);
            if (($slots['date'] ?? null) !== $payload['date'] || ($slots['timezone'] ?? null) !== 'America/Bogota'
                || ! collect($slots['slots'] ?? [])->contains(fn ($slot) => (is_string($slot) ? $slot : ($slot['time'] ?? null)) === $payload['time']
                    && (!is_array($slot) || ($slot['available'] ?? true) !== false)
                    && (!is_array($slot) || !isset($slot['staff_id']) || $slot['staff_id'] === $appointment->staff_id))
                || CarbonImmutable::parse($payload['date'].' '.$payload['time'], 'America/Bogota')->isPast()) {
                throw ValidationException::withMessages(['time' => 'El horario no está disponible con ese profesional.']);
            }
        }
        $operation = DB::transaction(function () use ($appointment, $actor, $uuid, $action, $payload) {
            KalendiaAppointment::whereKey($appointment->id)->lockForUpdate()->firstOrFail();
            if (KalendiaAppointmentAction::where('appointment_id', $appointment->id)->where('uuid', '!=', $uuid)
                ->whereIn('status', ['processing', 'uncertain'])->exists()) {
                throw ValidationException::withMessages(['appointment' => 'Resuelve primero el cambio pendiente de esta cita.']);
            }
            $row = KalendiaAppointmentAction::where('uuid', $uuid)->lockForUpdate()->first();
            if ($row) {
                abort_unless((int) $row->appointment_id === (int) $appointment->id
                    && $row->action === $action && $row->payload === $payload, 409);
                if ($row->status === 'succeeded') return $row;
                if ($row->leased_until?->isFuture()) throw ValidationException::withMessages(['appointment' => 'La operación sigue en curso.']);
            } else $row = new KalendiaAppointmentAction(['uuid' => $uuid, 'actor_id' => $actor->id,
                'appointment_id' => $appointment->id, 'action' => $action, 'payload' => $payload]);
            $row->forceFill(['status' => 'processing', 'lease' => (string) Str::uuid(), 'leased_until' => now()->addMinute()])->save();
            return $row;
        });
        if ($operation->status === 'succeeded') return;
        try {
            $result = $this->api->request('PATCH', 'appointments/'.$appointment->remote_id, $operation->payload, 'app-appointment-action-'.$operation->uuid);
            $data = $result['appointment'] ?? $result;
            $data['id'] = $appointment->remote_id;
            if ($action === 'cancel') $data['status'] = 'cancelled';
            else {
                $start = CarbonImmutable::parse($payload['date'].' '.$payload['time'], 'America/Bogota');
                $data['starts_at'] ??= $start->toIso8601String();
                $data['ends_at'] ??= $start->addSeconds((int) $appointment->starts_at->diffInSeconds($appointment->ends_at))->toIso8601String();
            }
            DB::transaction(function () use ($appointment,$operation,$result,$data) {
                KalendiaAppointment::whereKey($appointment->id)->lockForUpdate()->firstOrFail();
                $changed = KalendiaAppointmentAction::whereKey($operation->id)->where('lease', $operation->lease)->update(['status' => 'succeeded', 'http_status' => $result['_http_status'], 'lease' => null, 'leased_until' => null]);
                if ($changed) $this->record($data);
            });
        } catch (KalendiaApiException $error) {
            $rejected = !$replaying && (in_array($error->httpStatus, [400,401,403,404,422], true) || ($error->httpStatus === 409 && $error->errorCode === 'slot_unavailable'));
            KalendiaAppointmentAction::whereKey($operation->id)->where('lease', $operation->lease)->update(['status' => $rejected ? 'rejected' : 'uncertain', 'http_status' => $error->httpStatus, 'lease' => null, 'leased_until' => null]);
            throw ValidationException::withMessages(['appointment' => $rejected ? 'Kalendia rechazó el cambio. Revisa la disponibilidad y selecciona otro horario.'
                : 'Kalendia no confirmó el cambio. Reintenta esta misma operación desde la cita.']);
        }
    }
}
