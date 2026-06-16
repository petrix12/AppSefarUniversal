<?php

namespace App\Http\Controllers;

use App\Models\Compras;
use App\Models\ConsultationBooking;
use App\Models\ConsultationCalendar;
use App\Models\Servicio;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ServiceStoreController extends Controller
{
    public function index()
    {
        $services = Servicio::sellable()
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get()
            ->groupBy(fn (Servicio $servicio) => $servicio->categoria ?: 'general');

        return view('services.store.index', compact('services'));
    }

    public function show(Servicio $servicio)
    {
        abort_unless($this->isSellable($servicio), 404);

        $slotsByCalendar = $this->requiresBooking($servicio)
            ? $this->availableSlots($servicio)
            : [];

        return view('services.store.show', compact('servicio', 'slotsByCalendar'));
    }

    public function purchase(Request $request, Servicio $servicio)
    {
        abort_unless($this->isSellable($servicio), 404);

        $slot = null;
        if ($this->requiresBooking($servicio)) {
            $request->validate([
                'slot' => ['required', 'string'],
            ]);

            $slot = $this->findAvailableSlot($servicio, $request->input('slot'));

            if (! $slot) {
                return back()
                    ->withInput()
                    ->with('error', 'Ese horario ya no esta disponible. Selecciona otro.');
            }
        }

        DB::transaction(function () use ($servicio, $slot) {
            $description = $this->purchaseDescription($servicio, $slot);

            $compra = Compras::create([
                'id_user' => auth()->id(),
                'servicio_id' => $servicio->id,
                'source' => $slot ? 'consultation' : 'catalog',
                'servicio_hs_id' => $servicio->id_hubspot,
                'descripcion' => $description,
                'pagado' => 0,
                'monto' => $servicio->precio,
                'metadata' => [
                    'service_type' => $servicio->tipo,
                    'category' => $servicio->categoria,
                ],
            ]);

            if ($slot) {
                ConsultationBooking::create([
                    'consultation_calendar_id' => $slot['calendar']->id,
                    'servicio_id' => $servicio->id,
                    'user_id' => auth()->id(),
                    'compra_id' => $compra->id,
                    'starts_at' => $slot['starts_at_utc'],
                    'ends_at' => $slot['ends_at_utc'],
                    'timezone' => $slot['calendar']->timezone,
                    'status' => ConsultationBooking::STATUS_PENDING_PAYMENT,
                ]);
            }
        });

        return redirect()
            ->route('clientes.pay')
            ->with('success', 'Servicio agregado al pago.');
    }

    private function isSellable(Servicio $servicio): bool
    {
        return (bool) $servicio->activo && (bool) $servicio->visible_cliente;
    }

    private function requiresBooking(Servicio $servicio): bool
    {
        return (bool) $servicio->requiere_agenda || $servicio->tipo === 'consulta';
    }

    private function availableSlots(Servicio $servicio, int $days = 45): array
    {
        $calendars = ConsultationCalendar::active()
            ->with(['availabilityRules' => fn ($query) => $query->where('activo', true)])
            ->where(function ($query) use ($servicio) {
                $query->where('servicio_id', $servicio->id)
                    ->orWhereNull('servicio_id');
            })
            ->orderBy('nombre')
            ->get();

        $slotsByCalendar = [];

        foreach ($calendars as $calendar) {
            $timezone = $calendar->timezone ?: config('app.timezone');
            $now = Carbon::now($timezone)->addMinutes(30);
            $slots = [];

            for ($offset = 0; $offset <= $days; $offset++) {
                $day = Carbon::today($timezone)->addDays($offset);
                $rules = $calendar->availabilityRules->where('weekday', $day->dayOfWeek);

                foreach ($rules as $rule) {
                    $duration = (int) ($rule->slot_duration_minutes ?: $calendar->slot_duration_minutes ?: $servicio->duracion_minutos ?: 60);
                    $buffer = (int) ($rule->buffer_minutes ?: $calendar->buffer_minutes);

                    $cursor = $day->copy()->setTimeFromTimeString($rule->starts_at);
                    $windowEnd = $day->copy()->setTimeFromTimeString($rule->ends_at);

                    while ($cursor->copy()->addMinutes($duration)->lte($windowEnd)) {
                        $slotEnd = $cursor->copy()->addMinutes($duration);

                        if ($cursor->greaterThan($now) && ! $this->slotIsBlocked($calendar, $cursor, $slotEnd)) {
                            $token = base64_encode($calendar->id . '|' . $cursor->toIso8601String() . '|' . $duration);
                            $slots[$day->toDateString()][] = [
                                'token' => $token,
                                'label' => $cursor->format('H:i') . ' - ' . $slotEnd->format('H:i'),
                                'date_label' => $cursor->translatedFormat('d/m/Y'),
                            ];
                        }

                        $cursor = $slotEnd->addMinutes($buffer);
                    }
                }
            }

            if (! empty($slots)) {
                $slotsByCalendar[] = [
                    'calendar' => $calendar,
                    'slots' => $slots,
                ];
            }
        }

        return $slotsByCalendar;
    }

    private function findAvailableSlot(Servicio $servicio, string $token): ?array
    {
        foreach ($this->availableSlots($servicio) as $calendarSlots) {
            foreach ($calendarSlots['slots'] as $daySlots) {
                foreach ($daySlots as $slot) {
                    if (hash_equals($slot['token'], $token)) {
                        $decoded = base64_decode($token, true);

                        if (! $decoded) {
                            return null;
                        }

                        $parts = explode('|', $decoded);

                        if (count($parts) !== 3) {
                            return null;
                        }

                        [$calendarId, $isoDate, $duration] = $parts;
                        $calendar = $calendarSlots['calendar'];

                        if ((int) $calendarId !== (int) $calendar->id) {
                            return null;
                        }

                        $startsAt = Carbon::parse($isoDate, $calendar->timezone);
                        $duration = max(15, (int) $duration);
                        $endsAt = $startsAt->copy()->addMinutes($duration);

                        if ($this->slotIsBlocked($calendar, $startsAt, $endsAt)) {
                            return null;
                        }

                        return [
                            'calendar' => $calendar,
                            'starts_at' => $startsAt,
                            'ends_at' => $endsAt,
                            'starts_at_utc' => $startsAt->copy()->utc(),
                            'ends_at_utc' => $endsAt->copy()->utc(),
                        ];
                    }
                }
            }
        }

        return null;
    }

    private function slotIsBlocked(ConsultationCalendar $calendar, Carbon $startsAt, Carbon $endsAt): bool
    {
        $startsAtUtc = $startsAt->copy()->utc();
        $endsAtUtc = $endsAt->copy()->utc();

        $bookingExists = $calendar->bookings()
            ->whereIn('status', [
                ConsultationBooking::STATUS_PENDING_PAYMENT,
                ConsultationBooking::STATUS_PAID,
                ConsultationBooking::STATUS_CONFIRMED,
            ])
            ->where('starts_at', '<', $endsAtUtc)
            ->where('ends_at', '>', $startsAtUtc)
            ->exists();

        if ($bookingExists) {
            return true;
        }

        return $calendar->blackouts()
            ->where('starts_at', '<', $endsAtUtc)
            ->where('ends_at', '>', $startsAtUtc)
            ->exists();
    }

    private function purchaseDescription(Servicio $servicio, ?array $slot): string
    {
        if (! $slot) {
            return $servicio->nombre;
        }

        return sprintf(
            'Consultoria: %s - %s %s',
            $servicio->nombre,
            $slot['starts_at']->format('d/m/Y'),
            $slot['starts_at']->format('H:i')
        );
    }
}
