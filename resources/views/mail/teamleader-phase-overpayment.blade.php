<p>Se detectó un posible sobrepago en la fase 3 de un proyecto Teamleader.</p>

<ul>
    <li><strong>Cliente:</strong> {{ $user->name }} ({{ $user->email }})</li>
    <li><strong>Proyecto:</strong> {{ $payment['project_title'] ?? $payment['project_id'] ?? '-' }}</li>
    <li><strong>ID Teamleader:</strong> {{ $payment['project_id'] ?? '-' }}</li>
    <li><strong>Monto preestablecido:</strong> {{ format_money((float) data_get($payment, 'phase_data.effective_preestab_amount', 0), 2, ',', '.') }} EUR</li>
    <li><strong>Monto pagado:</strong> {{ format_money((float) data_get($payment, 'phase_data.effective_paid_amount', 0), 2, ',', '.') }} EUR</li>
    <li><strong>Exceso detectado:</strong> {{ format_money((float) data_get($payment, 'phase_data.overpaid_amount', 0), 2, ',', '.') }} EUR</li>
</ul>

<p>El pago se considera realizado; se requiere revisión interna del exceso.</p>
