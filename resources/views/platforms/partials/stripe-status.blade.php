@php
    $status = $payment['status'] ?? '';
    $eventType = $payment['type'] ?? '';
    $failedEvent = str_ends_with($eventType, '.failed') || str_ends_with($eventType, '.payment_failed');
    $tone = match (true) {
        $failedEvent || in_array($status, ['failed', 'rejected', 'uncollectible', 'lost'], true) => 'danger',
        in_array($status, ['succeeded', 'paid', 'won'], true) => 'success',
        in_array($status, ['pending', 'processing', 'open', 'requires_payment_method', 'requires_confirmation', 'requires_action', 'requires_capture', 'needs_response', 'warning_needs_response'], true) => 'pending',
        default => 'neutral',
    };
    $statusText = $failedEvent ? 'Fallido' : ($statusLabels[$status] ?? ($status === 'requires_action'
        ? 'Requiere autenticación'
        : \App\Services\PlatformPresentation::status($status)));
@endphp
<span class="stripe-status stripe-status-{{ $tone }}">{{ $statusText }}</span>
