<?php

namespace App\Services\Payments;

use App\Services\HubspotClientIdentityGuard;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class HubspotPaymentReceiptWriter
{
    private ?CrmPaymentSyncState $syncState = null;
    private const PAID_PROPERTIES = [
        1 => 'fase_1_pagado', 2 => 'fase_2_pagado', 3 => 'fase_3_pagado',
        98 => 'carta_nat_pagado', 99 => 'cil___fcje_pagado',
    ];

    public function sync(array $c, CrmPaymentSyncState $state): void
    {
        $this->syncState = $state;
        $user = $c['user'];
        $contact = $this->request('GET', '/crm/v3/objects/contacts/'.rawurlencode($user->hs_id), [
            'properties' => 'email,numero_de_pasaporte,hs_merged_object_ids',
        ]);
        app(HubspotClientIdentityGuard::class)->assertForUser($user, $contact);
        if (mb_strtolower(trim((string) data_get($contact, 'properties.email'))) !== mb_strtolower(trim($user->email))
            || ($user->passport && data_get($contact, 'properties.numero_de_pasaporte')
                && trim($user->passport) !== trim((string) data_get($contact, 'properties.numero_de_pasaporte')))) {
            throw new PaymentSyncReviewException('La identidad del contacto HubSpot no coincide con la App.');
        }
        $contactId = (string) ($contact['id'] ?? '');
        if ($contactId !== (string) $user->hs_id) {
            throw new PaymentSyncReviewException('HubSpot ha fusionado el contacto; revisar su enlace antes de registrar el pago.');
        }
        $dealId = (string) $c['deal']->hubspot_id;
        $contacts = $this->associationIds('deals', $dealId, 'contacts');
        if (! in_array($contactId, $contacts, true)) {
            throw new PaymentSyncReviewException('El negocio HubSpot no está asociado al contacto del cliente.');
        }

        // A non-billable tracking invoice mirrors the existing App document.
        // It creates no customer checkout, fiscal PDF, email, or second charge.
        $comment = "Pago COS [COS:{$c['reference']}] · Comprobante App {$c['invoice']->id} pagado · {$c['invoice_url']}";
        $invoiceId = $this->ensureObject($state, 'remote_invoice', 'invoices', 'hs_comments', $comment, [
            'hs_currency' => $c['currency'], 'hs_invoice_billable' => 'false',
            'hs_invoice_date' => $c['date'], 'hs_comments' => $comment,
        ]);
        $invoice = $this->request('GET', '/crm/v3/objects/invoices/'.$invoiceId, [
            'properties' => 'hs_invoice_billable,hs_currency,hs_invoice_status,hs_comments,hs_amount_billed,hs_amount_paid,hs_balance_due',
        ]);
        if ((string) data_get($invoice, 'properties.hs_comments') !== $comment
            || ! in_array(data_get($invoice, 'properties.hs_invoice_billable'), [false, 'false'], true)
            || data_get($invoice, 'properties.hs_currency') !== $c['currency']
            || data_get($invoice, 'properties.hs_invoice_status') === 'voided') {
            throw new PaymentSyncReviewException('La factura HubSpot vinculada no corresponde al comprobante de la App.');
        }
        $lineName = "Abono COS [COS:{$c['reference']}]";
        $lineId = $this->ensureObject($state, 'line_item', 'line_items', 'name', $lineName, [
            'name' => $lineName, 'description' => (string) $c['receipt']->descripcion,
            'quantity' => '1', 'price' => $c['amount'], 'hs_line_item_currency_code' => $c['currency'],
        ]);
        $line = $this->request('GET', '/crm/v3/objects/line_items/'.$lineId, ['properties' => 'name,price,quantity']);
        if (data_get($line, 'properties.name') !== $lineName
            || abs((float) data_get($line, 'properties.price') - (float) $c['amount']) > .001
            || (float) data_get($line, 'properties.quantity') !== 1.0) {
            throw new PaymentSyncReviewException('La línea de factura HubSpot no coincide con el importe del abono.');
        }
        $this->associate('invoices', $invoiceId, 'contacts', $contactId);
        $this->associate('invoices', $invoiceId, 'deals', $dealId);
        $this->associate('invoices', $invoiceId, 'line_items', $lineId);
        if (data_get($invoice, 'properties.hs_invoice_status') === 'draft') {
            $this->request('PATCH', '/crm/v3/objects/invoices/'.$invoiceId, ['properties' => ['hs_invoice_status' => 'open']]);
        }

        $paymentId = $this->ensureObject($state, 'payment', 'commerce_payments', 'hs_reference_number', $c['reference'], [
            'hs_reference_number' => $c['reference'], 'hs_initial_amount' => $c['amount'],
            'hs_currency_code' => $c['currency'], 'hs_initiated_date' => $c['date'],
            'hs_latest_status' => 'succeeded', 'hs_processor_type' => 'none',
            'hs_customer_email' => $user->email, 'hs_internal_comment' => $comment,
        ], [
            $this->association($contactId, 387), $this->association($dealId, 391), $this->association($invoiceId, 542),
        ]);
        $payment = $this->request('GET', '/crm/v3/objects/commerce_payments/'.$paymentId, [
            'properties' => 'hs_reference_number,hs_initial_amount,hs_currency_code,hs_customer_email,hs_latest_status,hs_initiated_date',
        ]);
        if (data_get($payment, 'properties.hs_reference_number') !== $c['reference']
            || abs((float) data_get($payment, 'properties.hs_initial_amount') - (float) $c['amount']) > .001
            || data_get($payment, 'properties.hs_currency_code') !== $c['currency']
            || mb_strtolower((string) data_get($payment, 'properties.hs_customer_email')) !== mb_strtolower($user->email)
            || data_get($payment, 'properties.hs_latest_status') !== 'succeeded') {
            throw new PaymentSyncReviewException('El pago externo de HubSpot no coincide con el abono confirmado.');
        }
        $paymentInvoices = $this->associationIds('commerce_payments', $paymentId, 'invoices');
        $paymentContacts = $this->associationIds('commerce_payments', $paymentId, 'contacts');
        if (array_diff($paymentInvoices, [$invoiceId]) || array_diff($paymentContacts, [$contactId])) {
            throw new PaymentSyncReviewException('El pago HubSpot ya está vinculado a otra factura o a otro cliente.');
        }
        $this->associate('commerce_payments', $paymentId, 'contacts', $contactId);
        $this->associate('commerce_payments', $paymentId, 'deals', $dealId);
        $this->associate('commerce_payments', $paymentId, 'invoices', $invoiceId);
        $confirmed = $this->request('GET', '/crm/v3/objects/invoices/'.$invoiceId, [
            'properties' => 'hs_invoice_status,hs_amount_billed,hs_amount_paid,hs_balance_due',
        ]);
        if (data_get($confirmed, 'properties.hs_invoice_status') !== 'paid'
            || ! is_numeric(data_get($confirmed, 'properties.hs_amount_billed'))
            || abs((float) data_get($confirmed, 'properties.hs_amount_billed') - (float) $c['amount']) > .001
            || abs((float) data_get($confirmed, 'properties.hs_amount_paid') - (float) $c['amount']) > .001
            || ! is_numeric(data_get($confirmed, 'properties.hs_balance_due'))
            || abs((float) data_get($confirmed, 'properties.hs_balance_due')) > .001) {
            throw new \RuntimeException('HubSpot aún no confirma la factura pagada con saldo cero.');
        }
        $this->appendPhasePayment($c);
        $state->save(['hubspot_deal_id' => $dealId, 'hubspot_contact_id' => $contactId,
            'document_url' => $c['invoice_url'], 'invoice_status' => 'paid']);
    }

    private function appendPhasePayment(array $c): void
    {
        $property = self::PAID_PROPERTIES[$c['phase']];
        $path = '/crm/v3/objects/deals/'.$c['deal']->hubspot_id;
        $deal = $this->request('GET', $path, ['properties' => $property]);
        $previous = trim((string) data_get($deal, 'properties.'.$property));
        $marker = "[COS:{$c['reference']}]";
        if (str_contains($previous, $marker)) {
            $entries = collect(app(\App\Services\TeamleaderProjectPaymentAnalyzer::class)->parseMoneyText($previous)['dated_entries'])
                ->where('cos_reference', $c['reference'])->values();
            if ($entries->count() !== 1 || abs((float) $entries[0]['amount'] - (float) $c['amount']) > .001
                || $entries[0]['currency'] !== $c['currency']) {
                throw new PaymentSyncReviewException('La referencia del abono HubSpot existe con otro importe o moneda.');
            }
            return;
        }
        $text = 'Abono '.$c['amount'].' EUR '.$c['receipt']->paid_at->format('Y-m-d').' '.$marker;
        $this->request('PATCH', $path, ['properties' => [$property => $previous === '' ? $text : $previous.' + '.$text]]);
    }

    private function ensureObject(CrmPaymentSyncState $state, string $key, string $type, string $property, string $reference, array $properties, array $associations = []): string
    {
        return $state->createOnce($key, function () use ($type, $property, $reference): ?string {
            $result = $this->request('POST', '/crm/v3/objects/'.$type.'/search', [
                'filterGroups' => [['filters' => [['propertyName' => $property, 'operator' => 'EQ', 'value' => $reference]]]],
                'properties' => [$property], 'limit' => 2,
            ]);
            if ((int) ($result['total'] ?? 0) > 1) {
                throw new PaymentSyncReviewException('Hay varios registros CRM con la misma referencia de pago.');
            }
            return isset($result['results'][0]['id']) ? (string) $result['results'][0]['id'] : null;
        }, function () use ($state, $key, $type, $properties, $associations): string {
            try {
                $result = $this->request('POST', '/crm/v3/objects/'.$type,
                    ['properties' => $properties, 'associations' => $associations]);
                return (string) ($result['id'] ?? '');
            } catch (RequestException $e) {
                // A definitive rejection did not create a record. Unknown 5xx/connection
                // outcomes retain the intent and can only be recovered by reference.
                if (in_array($e->response->status(), [400, 401, 403, 404, 422, 429], true)) {
                    $state->save([$key.'_create_started' => false]);
                }
                throw $e;
            }
        });
    }

    private function associate(string $from, string $fromId, string $to, string $toId): void
    {
        $this->request('PUT', "/crm/v4/objects/{$from}/{$fromId}/associations/default/{$to}/{$toId}");
    }

    private function association(string $id, int $type): array
    {
        return ['to' => ['id' => $id], 'types' => [['associationCategory' => 'HUBSPOT_DEFINED', 'associationTypeId' => $type]]];
    }

    private function associationIds(string $from, string $id, string $to): array
    {
        $ids = [];
        $after = null;
        for ($pageNumber = 1; $pageNumber <= 10; $pageNumber++) {
            $page = $this->request('GET', "/crm/v4/objects/{$from}/{$id}/associations/{$to}", array_filter(['limit' => 500, 'after' => $after]));
            foreach ($page['results'] ?? [] as $record) $ids[] = (string) $record['toObjectId'];
            $after = data_get($page, 'paging.next.after');
            if ($after === null) return $ids;
        }
        throw new \RuntimeException('No se ha completado la comprobación de asociaciones de HubSpot.');
    }

    private function request(string $method, string $path, array $data = []): array
    {
        $this->syncState?->save(['last_operation' => $method.' '.$path]);
        if (! config('services.hubspot.key')) throw new \RuntimeException('HubSpot no está configurado.');
        $mode = strtolower((string) config('services.hubspot.force_ip_resolve', 'v4'));
        $options = ['force_ip_resolve' => in_array($mode, ['v4', 'v6'], true) ? $mode : 'v4', 'verify' => true];
        foreach (array_filter([config('services.hubspot.ca_bundle'), ini_get('curl.cainfo'),
            ini_get('openssl.cafile'), base_path('storage/certs/cacert.pem')]) as $ca) {
            if (is_file($ca)) { $options['verify'] = $ca; break; }
        }
        $response = Http::withToken(config('services.hubspot.key'))->withOptions($options)->acceptJson()->asJson()
            ->connectTimeout(5)->timeout(20)->send($method, 'https://api.hubapi.com'.$path,
                $method === 'GET' ? ['query' => $data] : ['json' => $data]);
        $response->throw();
        return $response->json() ?? [];
    }
}
