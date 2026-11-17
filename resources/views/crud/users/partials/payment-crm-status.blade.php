@if(($cosViewRoleId ?? 0) === 1)
    @foreach($purchases as $purchase)
        @if(($purchase['source'] ?? null) === \App\Services\TeamleaderPhasePaymentService::INSTALLMENT_SOURCE || data_get($purchase, 'metadata.registration_payment') === true)
            @php
                $sync = data_get($purchase, 'metadata.crm_payment_sync.providers', []);
                $labels = ['pending' => 'Pendiente', 'processing' => 'En proceso', 'retry' => 'Pendiente de reintento', 'review' => 'Requiere revisión', 'synced' => 'Sincronizado'];
                $hs = $sync['hubspot'] ?? [];
                $tl = $sync['teamleader'] ?? [];
                $portal = (string) config('crm_sync.hubspot.portal_id');
                $remoteInvoice = (string) ($hs['remote_invoice_id'] ?? '');
                $canLinkInvoice = ctype_digit($portal) && ctype_digit($remoteInvoice);
            @endphp
            <div class="small text-muted mt-2">
                @if($remoteInvoice !== '')
                    <div>
                        @if($canLinkInvoice)
                            <a href="https://app.hubspot.com/contacts/{{ $portal }}/record/0-53/{{ $remoteInvoice }}" target="_blank" rel="noopener noreferrer">Factura HubSpot {{ $hs['invoice_number'] ?? '#'.$remoteInvoice }}</a>
                        @else
                            Factura HubSpot {{ $hs['invoice_number'] ?? '#'.$remoteInvoice }}
                        @endif
                        · {{ ($hs['invoice_status'] ?? '') === 'paid' ? 'Pagada' : 'Pendiente de confirmación' }}
                    </div>
                @endif
                <div>HubSpot: {{ $labels[$hs['status'] ?? ''] ?? 'Sincronización no iniciada' }}</div>
                <div>Teamleader: {{ $labels[$tl['status'] ?? ''] ?? 'Sincronización no iniciada' }}</div>
            </div>
        @endif
    @endforeach
@endif
