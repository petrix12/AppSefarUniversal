<?php

namespace App\Services;

use App\Models\Compras;
use App\Models\KalendiaBooking;
use App\Models\KalendiaBookingRequest;
use App\Models\KalendiaConsultationOrder;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class KalendiaBookingQueue
{
    private const BACKOFF_MINUTES = [1, 3, 10];

    public function __construct(private readonly KalendiaRegistrationBookingService $bookings) {}

    public function enqueue(User $user, int $purchaseId, array $input): ?KalendiaBookingRequest
    {
        if (! Schema::hasTable('kalendia_booking_requests') || ! Schema::hasTable('kalendia_bookings')) {
            throw ValidationException::withMessages(['booking' => 'La agenda se está preparando. Tu pago sigue registrado.']);
        }

        return DB::transaction(function () use ($user, $purchaseId, $input) {
            $owner = User::query()->lockForUpdate()->findOrFail($user->id);
            $purchase = $this->bookings->purchase($owner, $purchaseId);
            $booking = KalendiaBooking::where('compra_id', $purchase->id)->where('user_id', $owner->id)->first();
            if ($booking?->remote_id) {
                return null;
            }

            $request = KalendiaBookingRequest::where('compra_id', $purchase->id)
                ->where('user_id', $owner->id)->lockForUpdate()->first();
            if ($request?->status === KalendiaBookingRequest::COMPLETED) {
                return null;
            }
            if ($request && in_array($request->status, KalendiaBookingRequest::ACTIVE_STATUSES, true)) {
                return $request;
            }

            if ($purchase->source !== KalendiaConsultationOrder::SOURCE) {
                $active = KalendiaBookingRequest::where('user_id', $owner->id)
                    ->whereIn('status', KalendiaBookingRequest::ACTIVE_STATUSES)
                    ->whereHas('purchase', $this->initialPurchases(...))
                    ->first();
                if ($active) {
                    return $active;
                }
                $olderBooking = KalendiaBooking::where('user_id', $owner->id)
                    ->where('compra_id', '!=', $purchase->id)->whereIn('status', ['processing', 'uncertain'])
                    ->whereHas('purchase', $this->initialPurchases(...))->exists();
                if ($olderBooking) {
                    throw ValidationException::withMessages(['booking' => 'Ya tienes una reserva inicial por comprobar. Abre «Mi reunión» para enviar esa comprobación.']);
                }
            }

            // Reservations submitted before the queue existed already have the original provider payload.
            $replay = in_array($booking?->status, ['processing', 'uncertain'], true);
            $choice = $replay ? [] : Validator::make($input, [
                'date' => ['required', 'date_format:Y-m-d'],
                'slot' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'],
                'timezone' => ['nullable', 'string', 'timezone:all'],
            ])->validate();
            if (! $replay) {
                $this->bookings->validateDate($choice['date'], $choice['timezone'] ?? null);
            }

            $request ??= new KalendiaBookingRequest();
            $request->forceFill([
                'user_id' => $owner->id,
                'compra_id' => $purchase->id,
                'status' => KalendiaBookingRequest::QUEUED,
                'requested_date' => $choice['date'] ?? $booking?->date,
                'input' => $choice,
                'available_at' => now(),
                'attempts' => 0,
                'lease' => null,
                'leased_until' => null,
            ])->save();

            return $request;
        });
    }

    public function processNext(): ?string
    {
        $request = DB::transaction(function () {
            $now = now();
            $request = KalendiaBookingRequest::query()->where(function (Builder $query) use ($now) {
                $query->where(fn (Builder $due) => $due->where('status', KalendiaBookingRequest::QUEUED)->where('available_at', '<=', $now))
                    ->orWhere(fn (Builder $expired) => $expired->where('status', KalendiaBookingRequest::PROCESSING)->where('leased_until', '<=', $now));
            })->orderBy('available_at')->orderBy('id')->lockForUpdate()->first();

            if ($request) {
                $request->forceFill([
                    'status' => KalendiaBookingRequest::PROCESSING,
                    'attempts' => $request->attempts + 1,
                    'lease' => (string) Str::uuid(),
                    'leased_until' => $now->copy()->addMinutes(10),
                ])->save();
            }

            return $request;
        });

        if (! $request) {
            return null;
        }

        $result = $this->attempt($request);
        $delay = self::BACKOFF_MINUTES[$request->attempts - 1] ?? null;
        if ($result === KalendiaBookingRequest::QUEUED && $delay === null) {
            $result = KalendiaBookingRequest::REVIEW;
        }

        $updated = KalendiaBookingRequest::whereKey($request->id)->where('lease', $request->lease)->update([
            'status' => $result,
            'available_at' => $result === KalendiaBookingRequest::QUEUED ? now()->addMinutes($delay) : now(),
            'lease' => null,
            'leased_until' => null,
            ...($result !== KalendiaBookingRequest::QUEUED ? ['input' => null] : []),
        ]);
        if ($updated && $result === KalendiaBookingRequest::REVIEW) {
            Log::warning('Kalendia: solicitud de cita requiere revisión', ['request_id' => $request->id, 'purchase_id' => $request->compra_id]);
        }

        return $updated ? $result : null;
    }

    private function attempt(KalendiaBookingRequest $request): string
    {
        $booking = $this->existingBooking($request);
        if ($booking?->remote_id) {
            return KalendiaBookingRequest::COMPLETED;
        }

        $owner = User::find($request->user_id);
        if (! $owner || ! $owner->hasRole('Cliente') || ! $owner->can('cliente') || $owner->hasIndividualClientBlock()
            || $request->attempts > count(self::BACKOFF_MINUTES) + 1) {
            return KalendiaBookingRequest::REVIEW;
        }

        if ($this->unconfirmedBookingHasPassed($booking)) {
            return KalendiaBookingRequest::REVIEW;
        }

        try {
            $booking = $this->bookings->book($owner, $request->compra_id, $request->input ?? []);

            return $booking->remote_id ? KalendiaBookingRequest::COMPLETED : KalendiaBookingRequest::QUEUED;
        } catch (KalendiaApiException $exception) {
            return $exception->httpStatus === null || $exception->httpStatus === 429 || $exception->httpStatus >= 500
                ? KalendiaBookingRequest::QUEUED
                : KalendiaBookingRequest::REVIEW;
        } catch (ValidationException $exception) {
            $booking = $this->existingBooking($request);
            if ($booking?->remote_id) {
                return KalendiaBookingRequest::COMPLETED;
            }
            $errors = $exception->errors();
            if (isset($errors['date']) || isset($errors['slot'])
                || ($booking?->status === 'rejected' && (int) $booking->last_http_status === 409)) {
                return KalendiaBookingRequest::NEEDS_SLOT;
            }

            return in_array($booking?->status, ['processing', 'uncertain'], true)
                ? KalendiaBookingRequest::QUEUED
                : KalendiaBookingRequest::REVIEW;
        } catch (HttpExceptionInterface $exception) {
            return $exception->getStatusCode() >= 500
                ? KalendiaBookingRequest::QUEUED
                : KalendiaBookingRequest::REVIEW;
        } catch (\Throwable $exception) {
            Log::warning('Kalendia: no se pudo procesar la solicitud de cita', [
                'request_id' => $request->id, 'exception_type' => $exception::class,
            ]);

            return $this->existingBooking($request)?->remote_id
                ? KalendiaBookingRequest::COMPLETED
                : KalendiaBookingRequest::QUEUED;
        }
    }

    private function existingBooking(KalendiaBookingRequest $request): ?KalendiaBooking
    {
        return KalendiaBooking::where('compra_id', $request->compra_id)->where('user_id', $request->user_id)->first();
    }

    private function initialPurchases(Builder $query): void
    {
        $query->where(fn (Builder $source) => $source->whereNull('source')->orWhere('source', '!=', KalendiaConsultationOrder::SOURCE));
    }

    private function unconfirmedBookingHasPassed(?KalendiaBooking $booking): bool
    {
        return in_array($booking?->status, ['processing', 'uncertain'], true)
            && CarbonImmutable::parse($booking->date.' '.$booking->time, $booking->timezone)->lessThanOrEqualTo(CarbonImmutable::now());
    }
}
