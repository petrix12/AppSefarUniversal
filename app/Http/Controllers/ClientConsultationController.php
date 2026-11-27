<?php
namespace App\Http\Controllers;

use App\Models\{KalendiaBooking, KalendiaBookingRequest, KalendiaConsultationOffer, KalendiaConsultationOrder};
use App\Services\{KalendiaApiException, KalendiaAppointmentService, KalendiaConsultationCheckout, KalendiaConsultationCatalog, KalendiaRegistrationBookingService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ClientConsultationController extends Controller
{
    public function meetings(Request $request, KalendiaAppointmentService $appointments)
    {
        abort_unless($request->user()->hasRole('Cliente'), 403);
        $summary = $appointments->summary($request->user());
        $summary['appointments'] = Schema::hasTable('kalendia_appointments') ? \App\Models\KalendiaAppointment::where('user_id', $request->user()->id)
            ->orderByDesc('starts_at')->paginate(20)->withQueryString() : collect();
        $orders = Schema::hasTable('kalendia_consultation_orders') ? KalendiaConsultationOrder::where('user_id', $request->user()->id)
            ->with('purchase.kalendiaBooking')->latest()->paginate(10, ['*'], 'orders_page')->withQueryString() : collect();
        $bookingRequests = Schema::hasTable('kalendia_booking_requests') ? KalendiaBookingRequest::where('user_id', $request->user()->id)
            ->whereIn('status', ['queued', 'processing', 'needs_slot', 'review'])->with('purchase.servicio')->latest()->get() : collect();
        return response()->view('kalendia.client.meetings', compact('summary', 'orders', 'bookingRequests'))->header('Cache-Control', 'private, no-store');
    }
    public function catalog(Request $request, KalendiaConsultationCheckout $checkout, KalendiaConsultationCatalog $catalog)
    {
        abort_unless($request->user()->hasRole('Cliente'), 403);
        $apiUnavailable = false;
        if ($checkout->configured() && Schema::hasTable('kalendia_consultation_offers')) {
            try { $catalog->sync(); } catch (KalendiaApiException) { $apiUnavailable = true; }
        }
        $offers = Schema::hasTable('kalendia_consultation_offers') ? KalendiaConsultationOffer::where('active', true)
            ->where(fn ($query) => $query->whereNull('catalog_service_id')->orWhere('catalog_available', true))
            ->orderBy('type')->orderBy('name')->get() : collect();
        $ready = ! $apiUnavailable && $checkout->configured() && (int) $request->user()->pay === 2 && (int) $request->user()->contrato === 1;
        $offerReady = [];
        foreach ($offers as $offer) {
            try { $offerReady[$offer->id] = $ready && $offer->hasPrice() && $checkout->canReserve($offer->remote_service_id); }
            catch (KalendiaApiException) { $offerReady[$offer->id] = false; }
        }
        return view('kalendia.client.catalog', compact('offers', 'ready', 'offerReady', 'apiUnavailable'));
    }
    public function purchase(Request $request, KalendiaConsultationOffer $offer, KalendiaConsultationCheckout $checkout)
    {
        $data = $request->validate(['checkout_key' => ['required', 'uuid']]);
        try { $order = $checkout->order($request->user(), $offer, $data['checkout_key']); }
        catch (KalendiaApiException) {
            if ($request->expectsJson()) return response()->json(['message' => 'No podemos comprobar la agenda en este momento. Tu tarjeta no se ha cobrado.'], 503);
            return back()->withErrors(['consultation' => 'No podemos comprobar la agenda en este momento. Tu tarjeta no se ha cobrado.']);
        }
        if ($request->expectsJson()) return response()->json($this->checkoutData($order))->header('Cache-Control', 'private, no-store');
        return redirect()->route('clientes.consultations.checkout', $order);
    }
    public function checkout(Request $request, KalendiaConsultationOrder $order, KalendiaConsultationCheckout $checkout)
    {
        $this->own($request, $order, $checkout);
        if ($request->expectsJson()) return response()->json($this->checkoutData($order))->header('Cache-Control', 'private, no-store');
        if ($order->status === 'paid') return redirect()->route('clientes.consultations.reserve', $order->compra_id);
        return response()->view('kalendia.client.checkout', compact('order'))->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    }
    public function intent(Request $request, KalendiaConsultationOrder $order, KalendiaConsultationCheckout $checkout)
    {
        $this->own($request, $order, $checkout);
        return response()->json($checkout->begin($order))->header('Cache-Control', 'private, no-store');
    }
    public function complete(Request $request, KalendiaConsultationOrder $order, KalendiaConsultationCheckout $checkout)
    {
        $this->own($request, $order, $checkout);
        try { $checkout->verify($order); }
        catch (\Throwable) {
            if ($request->expectsJson()) return response()->json(['paid' => false, 'message' => 'Estamos comprobando el pago. Puedes consultar esta misma compra sin volver a pagar.'], 503)->header('Cache-Control', 'private, no-store');
            return redirect()->route('clientes.consultations.checkout', $order)->withErrors(['payment' => 'Estamos comprobando el pago. Puedes consultar esta misma compra sin volver a pagar.']);
        }
        $order->refresh();
        if ($request->expectsJson()) return response()->json([
            'paid' => $order->status === 'paid',
            'next_url' => $order->status === 'paid' ? route('clientes.consultations.reserve', $order->compra_id) : null,
            'message' => $order->status === 'paid' ? 'Pago confirmado. Elige la fecha y la hora.' : 'El pago todavía no está confirmado. Comprueba esta misma compra antes de volver a pagar.',
        ])->header('Cache-Control', 'private, no-store');
        return $order->status === 'paid' ? redirect()->route('clientes.consultations.reserve', $order->compra_id)
            : redirect()->route('clientes.consultations.checkout', $order)->with('success', 'El pago todavía no está confirmado.');
    }
    private function own(Request $request, KalendiaConsultationOrder $order, KalendiaConsultationCheckout $checkout): void
    {
        $checkout->assertClient($request->user());
        abort_unless((int) $order->user_id === (int) $request->user()->id, 404);
    }
    private function checkoutData(KalendiaConsultationOrder $order): array
    {
        return ['paid' => $order->status === 'paid', 'checkout_url' => route('clientes.consultations.checkout', $order),
            'complete_url' => route('clientes.consultations.complete', $order),
            'next_url' => $order->status === 'paid' ? route('clientes.consultations.reserve', $order->compra_id) : null,
            'html' => $order->status === 'paid' ? null : view('kalendia.client.partials.payment-form', compact('order'))->render()];
    }
    public function reserve(Request $request, int $purchase, KalendiaRegistrationBookingService $service)
    {
        $purchase = $service->purchase($request->user(), $purchase);
        abort_unless($request->user()->hasRole('Cliente') && $purchase->source === KalendiaConsultationOrder::SOURCE, 404);
        $schemaReady = Schema::hasTable('kalendia_bookings');
        $queueReady = Schema::hasTable('kalendia_booking_requests');
        $booking = $schemaReady ? KalendiaBooking::where('compra_id', $purchase->id)->first() : null;
        if ($booking?->remote_id) {
            if ($request->expectsJson()) return response()->json(['stage' => 'complete', 'message' => 'Tu consulta ya tiene una reserva registrada.', 'next_url' => route('clientes.meetings')])->header('Cache-Control', 'private, no-store');
            return redirect()->route('clientes.meetings');
        }
        $queuedRequest = $queueReady ? KalendiaBookingRequest::where('user_id', $request->user()->id)
            ->where('compra_id', $purchase->id)->first() : null;
        if ($queuedRequest && in_array($queuedRequest->status, KalendiaBookingRequest::ACTIVE_STATUSES, true)) {
            if ($request->expectsJson()) return response()->json(['stage' => 'complete', 'message' => 'Tu solicitud está registrada. Puedes seguir su estado en Mi agenda.', 'next_url' => route('clientes.meetings')])->header('Cache-Control', 'private, no-store');
            return redirect()->route('clientes.meetings')->with('info', 'Tu solicitud de consulta está en curso. Puedes consultar su estado en Mi agenda.');
        }
        $purchases = collect([$purchase]); $remoteService = null; $agendaUnavailable = ! $schemaReady || ! $queueReady;
        try {
            if (! $agendaUnavailable) $remoteService = $service->remoteService($purchase);
        } catch (KalendiaApiException) { $agendaUnavailable = true; }
        $bookingReady = ! $agendaUnavailable && $service->bookingReady($remoteService);
        if ($booking && in_array($booking->status, ['processing','uncertain'], true)) {
            $bookingReady = $queueReady && app(\App\Services\KalendiaApi::class)->configured();
        }
        $continueUrl = route('clientes.meetings'); $continueLabel = 'Volver a Mi agenda'; $today = now('America/Bogota')->format('Y-m-d');
        $data = compact('purchase', 'purchases', 'booking', 'queuedRequest', 'remoteService', 'agendaUnavailable', 'bookingReady', 'continueUrl', 'continueLabel', 'today');
        if ($request->expectsJson()) return response()->json(['stage' => 'schedule', 'html' => view('kalendia.client.partials.consultation-agenda', $data)->render()])->header('Cache-Control', 'private, no-store');
        return response()->view('clientes.meeting', $data)->header('Cache-Control', 'private, no-store');
    }
}
