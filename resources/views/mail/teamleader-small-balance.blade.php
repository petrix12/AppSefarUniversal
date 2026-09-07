<p>Se detectó un diferencial menor al mínimo de cobro en un registro de pago.</p>

<ul>
    <li><strong>Cliente:</strong> {{ $user->name }} ({{ $user->email }})</li>
    <li><strong>Proyecto:</strong> {{ $payment['project_title'] ?? $payment['project_id'] ?? '-' }}</li>
    <li><strong>Concepto:</strong> {{ data_get($payment, 'payment_data.payment_label', 'Pago') }}</li>
    <li><strong>Monto preestablecido:</strong> {{ number_format((float) data_get($payment, 'payment_data.effective_preestab_amount', 0), 2, ',', '.') }} EUR</li>
    <li><strong>Monto registrado:</strong> {{ number_format((float) data_get($payment, 'payment_data.effective_paid_amount', 0), 2, ',', '.') }} EUR</li>
    <li><strong>Diferencial:</strong> {{ number_format((float) data_get($payment, 'payment_data.balance_amount', 0), 2, ',', '.') }} EUR</li>
</ul>

<p>El diferencial no se publicó al cliente porque es menor a USD 50. Requiere validación interna.</p>
