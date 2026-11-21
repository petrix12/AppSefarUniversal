<?php

namespace App\Http\Controllers;

use App\Models\KalendiaBooking;
use App\Services\KalendiaApi;
use App\Services\KalendiaApiException;
use App\Services\KalendiaRegistrationBookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ClientMeetingController extends Controller
{
    private function onboardingRedirect(Request $request)
    {
        $user = $request->user();
        abort_unless($user->hasRole('Cliente'), 403);
        if ((int) $user->pay === 0) return redirect()->route('clientes.pay');
        if ((int) $user->pay !== 2) return redirect()->route('clientes.getinfo');
        if ((int) $user->contrato !== 1) return redirect()->route('cliente.contrato');
        return null;
    }

    public function listing(Request $request, KalendiaRegistrationBookingService $service)
    {
        if ($redirect = $this->onboardingRedirect($request)) return $redirect;
        if (\App\Services\RegistrationMeetingService::hasBookedInitialMeeting($request->user())) return redirect()->route('clientes.meetings');
        $request->validate(['purchase' => ['nullable', 'integer']]);
        $purchases = $service->eligiblePurchases($request->user())->where('source', '!=', \App\Models\KalendiaConsultationOrder::SOURCE)->values();
        $pendingId = Schema::hasTable('kalendia_bookings') ? KalendiaBooking::where('user_id', $request->user()->id)
            ->whereIn('compra_id', $purchases->pluck('id'))->whereIn('status', ['processing', 'uncertain'])->value('compra_id') : null;
        $purchase = $request->filled('purchase') ? $purchases->firstWhere('id', (int) $request->query('purchase'))
            : ($pendingId ? $purchases->firstWhere('id', $pendingId) : $purchases->first());
        abort_unless($purchase, 404);
        $schemaReady = Schema::hasTable('kalendia_bookings');
        $booking = $schemaReady ? KalendiaBooking::where('user_id', $request->user()->id)->where('compra_id', $purchase->id)->first() : null;
        $remoteService = null;
        $agendaUnavailable = ! $schemaReady;
        try {
            if ($schemaReady && ! $booking?->remote_id) $remoteService = $service->remoteService($purchase);
        } catch (KalendiaApiException) { $agendaUnavailable = true; }
        $bookingReady = $schemaReady && $service->bookingReady($remoteService);
        if ($booking && in_array($booking->status, ['processing', 'uncertain'], true)) {
            $bookingReady = $schemaReady && app(KalendiaApi::class)->configured();
        }
        $continueUrl = route('clientes.status');
        $today = now(config('kalendia.timezone'))->format('Y-m-d');
        return response()->view('clientes.meeting', compact('purchases', 'purchase', 'booking', 'remoteService',
            'agendaUnavailable', 'bookingReady', 'continueUrl', 'today'))->header('Cache-Control', 'private, no-store');
    }

    public function availability(Request $request, KalendiaRegistrationBookingService $service)
    {
        abort_unless($request->user()->hasRole('Cliente'), 403);
        $data = $request->validate(['purchase' => ['required', 'integer'], 'date' => ['required', 'date_format:Y-m-d'], 'timezone'=>['bail','nullable','string','timezone:all']]);
        $purchase = $service->purchase($request->user(), (int) $data['purchase']);
        try {
            $slots = $service->availableSlots($purchase, $data['date'], $data['timezone'] ?? null);
        } catch (KalendiaApiException) {
            return response()->json(['message' => 'No podemos consultar la agenda en este momento. Inténtalo de nuevo.'], 503);
        }
        return response()->json(['date' => $data['date'], 'timezone' => \App\Services\KalendiaTimezone::validate($data['timezone'] ?? null), 'slots' => $slots])
            ->header('Cache-Control', 'private, no-store');
    }

    public function book(Request $request, int $purchase, KalendiaRegistrationBookingService $service)
    {
        abort_unless($request->user()->hasRole('Cliente'), 403);
        $service->book($request->user(), $purchase, $request->only(['date', 'slot', 'timezone']));
        return redirect()->route('clientes.meetings')->with('success', 'La agenda ha recibido tu reserva.');
    }
}
