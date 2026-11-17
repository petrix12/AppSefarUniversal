<?php

namespace App\Services\Payments;

use App\Services\TeamleaderProjectPaymentWriter;
use App\Services\TeamleaderService;
use Illuminate\Http\Client\RequestException;

class TeamleaderPaymentReceiptWriter
{
    public function __construct(private readonly TeamleaderService $teamleader)
    {
    }

    public function sync(array $c, CrmPaymentSyncState $state): void
    {
        $registration = ($c['kind'] ?? 'phase') === 'registration';
        if (! $c['user']->tl_id) throw new \RuntimeException('El contacto Teamleader del registro todavía no está enlazado.');
        if ($registration) {
            $state->save(['last_operation' => 'contacts.info']);
            $contact = $this->teamleader->webhookObject('contact', (string) $c['user']->tl_id);
            $emails = collect($contact['emails'] ?? [])->pluck('email')->map(fn ($v) => mb_strtolower(trim((string) $v)));
            if (! $contact || ! $emails->contains(mb_strtolower(trim($c['user']->email)))) {
                throw new PaymentSyncReviewException('La identidad del contacto Teamleader no coincide con la App.');
            }
        } else {
            $state->save(['last_operation' => 'projects.info']);
            $project = $this->teamleader->webhookObject('project', $c['project_id']);
            if (($project['id'] ?? '') !== $c['project_id']) {
                throw new PaymentSyncReviewException('Teamleader devolvió un proyecto diferente al del abono.');
            }
            $state->save(['last_operation' => 'contacts.info']);
            $contact = $this->teamleader->webhookObject('contact', (string) $c['user']->tl_id);
            $emails = collect($contact['emails'] ?? [])->pluck('email')->map(fn ($v) => mb_strtolower(trim((string) $v)));
            if (! $contact || ! $emails->contains(mb_strtolower(trim($c['user']->email)))) {
                throw new PaymentSyncReviewException('La identidad del contacto Teamleader no coincide con la App.');
            }
            $customer = $project['customer'] ?? [];
            $matches = ($customer['type'] ?? '') === 'contact' && ($customer['id'] ?? '') === (string) $c['user']->tl_id;
            if (($customer['type'] ?? '') === 'company') {
                $matches = collect($contact['companies'] ?? [])->contains(
                    fn ($company) => (string) ($company['id'] ?? data_get($company, 'company.id')) === (string) ($customer['id'] ?? ''));
            }
            if (! $matches) throw new PaymentSyncReviewException('El cliente del proyecto Teamleader no coincide con el abono.');

            $state->save(['phase_update_started' => true, 'last_operation' => 'projects.info/update.payment-field']);
            app(TeamleaderProjectPaymentWriter::class)->appendPhasePayment(
                $c['project_id'], $c['phase'], (float) $c['amount'],
                $c['receipt']->paid_at->format('Y-m-d'), $c['reference'], $c['currency'], $project,
            );
        }
        $subjectType = $registration ? 'contact' : 'project';
        $subjectId = $registration ? (string) $c['user']->tl_id : $c['project_id'];
        $marker = "[COS:{$c['reference']}]";
        $content = (($registration ? 'Registro confirmado ' : 'Pago confirmado ')."{$c['amount']} {$c['currency']} · ").$c['receipt']->paid_at->format('Y-m-d')
            ."\n{$marker}\nComprobante App {$c['invoice']->id}: PAGADO\n{$c['invoice_url']}"
            ."\nNegocio HubSpot: ".($c['deal']?->hubspot_id ?? 'No asignado')."\nRegistro del mismo pago; no supone un nuevo cobro.";
        $state->createOnce('note', function () use ($c, $marker, $state, $subjectId, $subjectType): ?string {
            $found = [];
            for ($page = 1; $page <= 10; $page++) {
                $state->save(['last_operation' => 'notes.list']);
                $notes = $this->teamleader->paymentReceiptNotes($subjectId, $page, $subjectType);
                foreach ($notes as $note) {
                    if (str_contains((string) ($note['content'] ?? ''), $marker)) $found[] = (string) $note['id'];
                }
                if (count($notes) < 100) {
                    if (count($found) > 1) throw new PaymentSyncReviewException('Teamleader contiene varias notas con la referencia del abono.');
                    return $found[0] ?? null;
                }
            }
            throw new \RuntimeException('No se ha completado la búsqueda del comprobante en Teamleader.');
        }, function () use ($state, $c, $content, $subjectId, $subjectType): string {
            try {
                $state->save(['last_operation' => 'notes.create']);
                return $this->teamleader->createPaymentReceiptNote($subjectId, $content, $subjectType);
            } catch (RequestException $e) {
                if (in_array($e->response->status(), [400, 401, 403, 404, 422, 429], true)) {
                    $state->save(['note_create_started' => false]);
                }
                throw $e;
            }
        });
        $state->save(['teamleader_project_id' => $c['project_id'], 'document_url' => $c['invoice_url'], 'document_status' => 'paid']);
    }
}
