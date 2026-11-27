<h3 class="h5" data-consultation-name>{{ $order->name }}</h3>
<p class="text-muted">{{ $order->type }}</p>
<p class="h4">{{ format_money($order->amount, 2) }} EUR</p>
<p>Después de confirmar el pago elegirás la fecha y la hora de tu consulta.</p>
<form data-consultation-payment data-intent-url="{{ route('clientes.consultations.intent', $order) }}" data-return-url="{{ route('clientes.consultations.complete', $order) }}" data-stripe-key="{{ config('services.stripe.key') }}">
    @csrf
    <div data-payment-element></div>
    <p data-payment-message role="status" aria-live="polite" class="mt-3"></p>
    <button type="submit" class="btn btn-primary" data-payment-submit disabled>Pagar {{ format_money($order->amount, 2) }} EUR</button>
</form>
<a class="btn btn-link mt-3" href="{{ route('clientes.consultations.complete', $order) }}" data-consultation-check>Comprobar el estado de este pago</a>
