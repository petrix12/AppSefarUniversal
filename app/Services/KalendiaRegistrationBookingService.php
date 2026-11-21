<?php

namespace App\Services;

use App\Models\Compras;
use App\Models\KalendiaBooking;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class KalendiaRegistrationBookingService
{
    public function __construct(private KalendiaApi $api) {}

    public function eligiblePurchases(User $user): Collection
    {
        return Compras::with(['factura', 'servicio'])->where('id_user', $user->id)->where('pagado', 1)
            ->whereNull('deal_id')->orderBy('id')->get()->filter(function ($purchase) use ($user) {
                return $this->serviceName($purchase) !== null
                    && $purchase->factura && (int) $purchase->factura->id_cliente === (int) $user->id
                    && in_array($purchase->factura->met, ['stripe', 'paypal', 'cupon'], true)
                    && ! in_array($purchase->source, [TeamleaderPhasePaymentService::PURCHASE_SOURCE,
                        TeamleaderPhasePaymentService::INSTALLMENT_SOURCE, TeamleaderPhasePaymentService::HISTORY_SOURCE], true);
            })->values();
    }

    public function purchase(User $user, int $id): Compras
    {
        abort_unless((int) $user->pay === 2 && (int) $user->contrato === 1, 403,
            'Completa primero el pago, el formulario 001 y el contrato.');
        $purchase = $this->eligiblePurchases($user)->firstWhere('id', $id);
        abort_unless($purchase, 404);
        if ($purchase->source !== \App\Models\KalendiaConsultationOrder::SOURCE && RegistrationMeetingService::hasBookedInitialMeeting($user)) {
            abort_unless(KalendiaBooking::where('compra_id', $id)->whereNotNull('remote_id')->exists(), 403,
                'La reunión inicial ya fue utilizada. Contrata una consulta para agendar otra reunión.');
        }
        return $purchase;
    }

    public function serviceName(Compras $purchase): ?string
    {
        if ($purchase->source === \App\Models\KalendiaConsultationOrder::SOURCE) {
            return filled(data_get($purchase->metadata, 'kalendia_consultation.remote_service_id'))
                ? data_get($purchase->metadata, 'kalendia_consultation.service_name') : null;
        }
        foreach ([$purchase->servicio?->nombre, $purchase->servicio_hs_id] as $name) {
            if (RegistrationMeetingService::supports($name)) return $name;
        }
        return null;
    }

    public function validateDate(string $date, ?string $timezone = null): CarbonImmutable
    {
        $timezone = KalendiaTimezone::validate($timezone);
        Validator::make(['date' => $date], ['date' => ['required', 'date_format:Y-m-d']])->validate();
        $day = CarbonImmutable::createFromFormat('!Y-m-d', $date, $timezone);
        if ($day->isWeekend() || $day->lt(CarbonImmutable::today($timezone))) {
            throw ValidationException::withMessages(['date' => 'Selecciona una fecha de lunes a viernes que no haya pasado.']);
        }
        return $day;
    }

    public function remoteService(Compras $purchase): ?array
    {
        if ($purchase->source === \App\Models\KalendiaConsultationOrder::SOURCE) return $this->api->serviceById(
            (string) data_get($purchase->metadata, 'kalendia_consultation.remote_service_id'));
        return $this->api->serviceFor($this->serviceName($purchase) ?? '');
    }

    public function availableSlots(Compras $purchase, string $date, ?string $timezone = null): array
    {
        $timezone = KalendiaTimezone::validate($timezone);
        $day = $this->validateDate($date, $timezone);
        $service = $this->remoteService($purchase);
        if (! $service) return [];
        $first = $day->setTimezone('America/Bogota')->startOfDay();
        $last = $day->endOfDay()->setTimezone('America/Bogota')->startOfDay();
        $slots = [];
        for ($sourceDay = $first; $sourceDay->lte($last); $sourceDay = $sourceDay->addDay()) {
        if ($sourceDay->isWeekend() || $sourceDay->lt(CarbonImmutable::today('America/Bogota'))) continue;
        $sourceDate = $sourceDay->format('Y-m-d');
        $result = $this->api->request('GET', 'availability', [
            'service_id' => $service['id'], 'date' => $sourceDate, 'timezone' => config('kalendia.timezone'),
        ]);
        if (($result['date'] ?? null) !== $sourceDate || ($result['timezone'] ?? null) !== config('kalendia.timezone')) {
            throw new KalendiaApiException();
        }
        foreach ($result['slots'] ?? [] as $slot) {
            $time = is_string($slot) ? $slot : ($slot['time'] ?? null);
            $staff = is_array($slot) ? ($slot['staff_id'] ?? null) : null;
            if (is_array($slot) && $staff === null && array_key_exists('staffId', $slot)) {
                // The live availability API uses numeric staffId; bookings require the opaque ID from /staff.
                $staff = is_string($slot['staff'] ?? null) ? $this->api->staffForName($slot['staff']) : null;
                if (! $staff) continue;
            }
            if (! is_string($time) || ! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $time)
                || (is_array($slot) && ($slot['available'] ?? true) === false)
                || ($staff !== null && (! is_string($staff) || ! preg_match('/^stf_[A-Za-z0-9_-]+$/D', $staff)))) continue;
            $starts = CarbonImmutable::createFromFormat('!Y-m-d H:i', $sourceDate.' '.$time, config('kalendia.timezone'));
            $local = $starts->setTimezone($timezone);
            if ($starts->lessThanOrEqualTo(CarbonImmutable::now()) || $local->format('Y-m-d') !== $date) continue;
            $slots[] = ['date' => $sourceDate, 'time' => $time, 'display_time' => $local->format('H:i'), 'starts_at' => $starts->toIso8601String(), 'staff_id' => $staff,
                'key' => hash('sha256', $sourceDate.'|'.$time.'|'.($staff ?? '').'|'.$service['id'].'|'.$timezone)];
        }
        }
        return collect($slots)->unique('starts_at')->sortBy('starts_at')->values()->all();
    }

    public function bookingReady(?array $service = null): bool
    {
        return $this->api->configured() && ($this->api->isFreeService($service) || filled(config('kalendia.prepaid_payment_method')));
    }

    public function book(User $user, int $purchaseId, array $input): KalendiaBooking
    {
        $purchase = $this->purchase($user, $purchaseId);
        if (! Schema::hasTable('kalendia_bookings')) throw ValidationException::withMessages(['booking' => 'La agenda se está preparando. Tu pago se conserva; inténtalo más tarde.']);
        $prior = KalendiaBooking::where('compra_id', $purchase->id)->first();
        if ($prior?->remote_id) return $prior;
        $retry = $prior && in_array($prior->status, ['processing', 'uncertain'], true);
        $service = $retry ? null : $this->remoteService($purchase);
        if (! $this->api->configured() || (! $retry && ! $this->bookingReady($service))) {
            throw ValidationException::withMessages(['booking' => 'Tu pago está confirmado en la App. Estamos habilitando la reserva de tu reunión sin un nuevo cobro.']);
        }
        $payload = null;
        $slot = null;
        if (! $retry) {
            $timezone = KalendiaTimezone::validate($input['timezone'] ?? null);
            $data = Validator::make($input, ['date' => ['required', 'date_format:Y-m-d'],
                'slot' => ['required', 'string', 'size:64']])->validate();
            $slot = collect($this->availableSlots($purchase, $data['date'], $timezone))->firstWhere('key', $data['slot']);
            if (! $service || ! $slot) throw ValidationException::withMessages(['slot' => 'Ese horario ya no está disponible. Consulta la agenda y elige otro.']);
            $currency = strtoupper((string) data_get($purchase->metadata, 'display_currency', 'EUR'));
            abort_unless(preg_match('/^[A-Z]{3}$/D', $currency) && is_finite((float) $purchase->monto) && $purchase->monto >= 0, 422);
            $amount = number_format((float) $purchase->monto, 2, '.', '');
            $reference = (string) data_get($purchase->metadata, 'gateway_reference', $purchase->hash_factura);
            $customer = array_filter(['name' => $user->name, 'email' => $user->email, 'phone' => $user->phone,
                'timezone' => $timezone, 'properties' => ['cf_nopasaporte' => $user->passport]], fn ($v) => $v !== null && $v !== '');
            $payload = ['user_timezone' => $timezone, 'customer' => $customer, 'appointment' => [
                'service_id' => $service['id'], 'date' => $slot['date'], 'time' => $slot['time'],
                'timezone' => config('kalendia.timezone'),
                'customer' => array_intersect_key($customer, array_flip(['name', 'email', 'phone'])),
                'notes' => 'Reunión incluida en el servicio contratado. Pago confirmado en la App: '.$amount.' '.$currency
                    .'. Factura App #'.$purchase->factura->id.'. Compra #'.$purchase->id.'. Referencia: '.$reference
                    .'. Comprobante: '.url('/viewcomprobante/'.$purchase->factura->id)
                    .'. Horario del cliente: '.$data['date'].' '.$slot['display_time'].' ('.$timezone.').',
            ]];
            if (! $this->api->isFreeService($service)) $payload['appointment']['payment_method'] = (string) config('kalendia.prepaid_payment_method');
            if ($slot['staff_id']) $payload['appointment']['staff_id'] = $slot['staff_id'];
        }

        [$booking, $replaying] = DB::transaction(function () use ($purchase, $user, $payload, $service, $slot, $input) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($purchase->source !== \App\Models\KalendiaConsultationOrder::SOURCE) {
                $other = KalendiaBooking::where('user_id', $user->id)->where('compra_id', '!=', $purchase->id)
                    ->whereHas('purchase', fn ($q) => $q->where(fn ($source) => $source->whereNull('source')->orWhere('source', '!=', \App\Models\KalendiaConsultationOrder::SOURCE)))
                    ->where(fn ($q) => $q->whereNotNull('remote_id')->orWhereIn('status', ['processing', 'uncertain']))->exists();
                if ($other) throw ValidationException::withMessages(['booking' => 'Tu reunión inicial ya está reservada o en comprobación. Para otra reunión, contrata una consulta.']);
            }
            // Serialize initial creation as well as retries of the same purchase.
            $locked = Compras::whereKey($purchase->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->pagado && (int) $locked->id_user === (int) $user->id
                && $locked->hash_factura === $purchase->hash_factura && $locked->monto === $purchase->monto, 409);
            $booking = KalendiaBooking::where('compra_id', $purchase->id)->lockForUpdate()->first();
            if ($booking?->remote_id) return [$booking, false];
            if ($booking?->status === 'processing' && $booking->leased_until?->isFuture()) {
                throw ValidationException::withMessages(['booking' => 'Estamos procesando tu reserva. Espera unos segundos antes de volver a consultar.']);
            }
            $replaying = $booking && in_array($booking->status, ['processing', 'uncertain'], true);
            if (! $booking || $booking->status === 'rejected') {
                // Another request may have completed or claimed the purchase while availability was read.
                if (! $payload) throw ValidationException::withMessages(['booking' => 'Vuelve a consultar la agenda antes de reservar.']);
                $booking ??= new KalendiaBooking(['compra_id' => $purchase->id, 'user_id' => $user->id]);
                $booking->forceFill(['idempotency_key' => (string) Str::uuid(), 'request_payload' => $payload,
                    'remote_service_id' => $service['id'], 'date' => $slot['date'], 'time' => $slot['time'],
                    'timezone' => config('kalendia.timezone'), 'paid_amount' => $purchase->monto,
                    'currency' => strtoupper((string) data_get($purchase->metadata, 'display_currency', 'EUR')),
                    'remote_customer_id' => null]);
            }
            $booking->forceFill(['status' => 'processing', 'lease' => (string) Str::uuid(), 'leased_until' => now()->addMinutes(2)])->save();
            return [$booking, $replaying];
        });
        if ($booking->remote_id) return $booking;
        $body = $booking->request_payload;
        try {
            if (! $booking->remote_customer_id) {
                $customer = $this->api->request('POST', 'customers', $body['customer'], 'app-customer-'.$booking->idempotency_key);
                $id = $customer['id'] ?? data_get($customer, 'customer.id');
                if (! is_string($id) || ! preg_match('/^cli_[A-Za-z0-9_-]{1,200}$/D', $id)) throw new KalendiaApiException();
                if (! KalendiaBooking::whereKey($booking->id)->where('lease', $booking->lease)->update(['remote_customer_id' => $id])) {
                    throw ValidationException::withMessages(['booking' => 'La reserva se está comprobando en otra solicitud. Vuelve a consultar su estado.']);
                }
            }
            $result = $this->api->request('POST', 'appointments', $body['appointment'], 'app-appointment-'.$booking->idempotency_key);
            $appointment = $result['appointment'] ?? $result;
            $id = $appointment['id'] ?? null;
            if (! is_string($id) || ! preg_match('/^apt_[A-Za-z0-9_-]{1,200}$/D', $id)) throw new KalendiaApiException();
            $link = $appointment['virtual_meeting_url'] ?? null;
            KalendiaBooking::whereKey($booking->id)->where('lease', $booking->lease)->update(['remote_id' => $id, 'status' => 'booked', 'remote_status' => $appointment['status'] ?? 'pending',
                'meeting_url' => is_string($link) && filter_var($link, FILTER_VALIDATE_URL) && parse_url($link, PHP_URL_SCHEME) === 'https' ? $link : null,
                'lease' => null, 'leased_until' => null, 'last_http_status' => $result['_http_status']]);
        } catch (KalendiaApiException $exception) {
            $unavailable = $exception->httpStatus === 409 && $exception->errorCode === 'slot_unavailable';
            $rejected = ! $replaying && (in_array($exception->httpStatus, [400, 401, 403, 404, 422], true) || $unavailable);
            KalendiaBooking::whereKey($booking->id)->where('lease', $booking->lease)->update([
                'status' => $rejected ? 'rejected' : 'uncertain', 'lease' => null, 'leased_until' => null,
                'last_http_status' => $exception->httpStatus]);
            throw ValidationException::withMessages(['booking' => $rejected
                ? ($unavailable ? 'Ese horario ya no está disponible. Tu pago se conserva. Consulta la agenda y selecciona otro horario.'
                    : 'La agenda no pudo aceptar la reserva. Tu pago se conserva; vuelve a consultar los horarios o contacta con atención al cliente.')
                : 'Aún no tenemos confirmación de la agenda. Usa «Comprobar reserva» para repetir la misma solicitud sin crear otra cita.']);
        }
        $booking = $booking->fresh();
        if ($booking->remote_id && ! $booking->meeting_url) $this->loadMeetingLink($booking);
        try { app(KalendiaAppointmentService::class)->recordBooking($booking->fresh()); }
        catch (KalendiaApiException) { /* The confirmed booking is retained if enrichment is temporarily unavailable. */ }
        return $booking->fresh();
    }

    private function loadMeetingLink(KalendiaBooking $booking): void
    {
        // Creation returns no meeting URL in the live API. Save its ID first; a failed read must never replay a confirmed creation.
        try {
            for ($page = 1; $page <= 3; $page++) {
                $result = $this->api->request('GET', 'appointments', ['updated_since' => $booking->created_at->subMinutes(5)->toIso8601String(),
                    'page' => $page, 'per_page' => 100]);
                $items = $result['items'] ?? [];
                if (! is_array($items)) return;
                foreach ($items as $appointment) {
                    if (! is_array($appointment)) continue;
                    if (($appointment['id'] ?? null) !== $booking->remote_id) continue;
                    if (($appointment['client_id'] ?? null) !== $booking->remote_customer_id
                        || ($appointment['service_id'] ?? null) !== $booking->remote_service_id) return;
                    app(KalendiaAppointmentService::class)->record($appointment, true);
                    $link = $appointment['virtual_meeting_url'] ?? null;
                    if (is_string($link) && filter_var($link, FILTER_VALIDATE_URL) && parse_url($link, PHP_URL_SCHEME) === 'https') {
                        $booking->forceFill(['meeting_url' => $link])->save();
                    }
                    return;
                }
                if (count($items) < 100) return;
            }
        } catch (KalendiaApiException) {
            // The appointment is already booked; its access details can still arrive in the provider's confirmation.
        }
    }
}
