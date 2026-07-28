<?php

namespace App\Services;

use App\Models\HubspotOwner;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class ClientSupportTicketService
{
    private const SUPPORT_EMAILS = [
        'sistemasccs@sefarvzla.com',
        'info@sefarvzla.com',
    ];

    public function __construct(private HubspotService $hubspot)
    {
    }

    public function create(User $client, User $requester, string $description, string $source, bool $isTest = false): array
    {
        $description = trim($description);

        if ($description === '') {
            throw new \InvalidArgumentException('La solicitud no puede estar vacia.');
        }

        $contact = $this->resolveContact($client);
        $contactId = (string) ($contact['id'] ?? '');
        $contactProperties = $contact['properties'] ?? [];
        $ownerId = trim((string) ($contactProperties['hubspot_owner_id'] ?? $client->hubspot_owner_id ?? ''));

        if ($contactId === '') {
            throw new \RuntimeException('No se pudo ubicar el contacto del cliente en HubSpot.');
        }

        if ($ownerId === '') {
            throw new \RuntimeException('El contacto no tiene propietario asignado en HubSpot.');
        }

        $owner = $this->resolveOwner($ownerId);
        $ownerEmail = strtolower(trim((string) ($owner['email'] ?? '')));

        if ($ownerEmail === '') {
            throw new \RuntimeException('El propietario del contacto en HubSpot no tiene correo disponible.');
        }

        $this->syncLocalOwnerData($client, $ownerId, $owner);

        $subject = ($isTest ? '[PRUEBA] ' : '') . 'Solicitud de soporte del cliente: ' . $this->clientLabel($client);
        $content = $this->buildTicketContent($client, $requester, $description, $source, $owner, $isTest);

        $ticket = $this->hubspot->createTicket([
            'subject' => $subject,
            'content' => $content,
            'hs_pipeline' => env('HUBSPOT_SUPPORT_TICKET_PIPELINE', '0'),
            'hs_pipeline_stage' => env('HUBSPOT_SUPPORT_TICKET_STAGE', '1'),
            'hs_ticket_priority' => env('HUBSPOT_SUPPORT_TICKET_PRIORITY', 'MEDIUM'),
            'hubspot_owner_id' => $ownerId,
        ], $contactId);

        $recipients = $this->recipients($ownerEmail);
        $this->sendEmail($recipients, $subject, $content, $ticket, $owner, $client);

        Log::info('Solicitud de soporte creada desde App Sefar.', [
            'client_id' => $client->id,
            'requester_id' => $requester->id,
            'hubspot_contact_id' => $contactId,
            'hubspot_owner_id' => $ownerId,
            'hubspot_ticket_id' => $ticket['id'] ?? null,
            'recipients' => $recipients,
            'source' => $source,
            'is_test' => $isTest,
        ]);

        return [
            'ticket_id' => $ticket['id'] ?? null,
            'owner_email' => $ownerEmail,
            'owner_id' => $ownerId,
            'recipients' => $recipients,
        ];
    }

    private function resolveContact(User $client): array
    {
        $contact = null;

        if (! empty($client->hs_id)) {
            try {
                $contact = $this->hubspot->getContactOwnerById((string) $client->hs_id);
            } catch (\Throwable $e) {
                Log::warning('No se pudo obtener contacto por hs_id para solicitud de soporte.', [
                    'client_id' => $client->id,
                    'hs_id' => $client->hs_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (! $contact && ! empty($client->email)) {
            $contact = $this->hubspot->searchContactOwnerByEmail($client->email);
        }

        if (! $contact && ! empty($client->passport)) {
            $passportContact = $this->hubspot->searchContactByPassport($client->passport);
            if (! empty($passportContact['id'])) {
                $contact = $this->hubspot->getContactOwnerById((string) $passportContact['id']);
            }
        }

        if (! $contact || empty($contact['id'])) {
            throw new \RuntimeException('No se encontro el contacto del cliente en HubSpot.');
        }

        if ((string) $client->hs_id !== (string) $contact['id']) {
            $client->forceFill(['hs_id' => (string) $contact['id']])->save();
        }

        return $contact;
    }

    private function resolveOwner(string $ownerId): array
    {
        $localOwner = HubspotOwner::find($ownerId);
        $owner = $this->hubspot->getOwnerById($ownerId);

        if (! $owner && $localOwner && ! empty($localOwner->email)) {
            return [
                'id' => (string) $localOwner->id,
                'email' => $localOwner->email,
                'name' => $localOwner->name,
                'active' => $localOwner->active,
            ];
        }

        if (! $owner) {
            throw new \RuntimeException('No se pudo obtener el propietario del contacto en HubSpot.');
        }

        $ownerName = $this->ownerName($owner);

        HubspotOwner::updateOrCreate(
            ['id' => (string) $owner['id']],
            [
                'email' => $owner['email'] ?? null,
                'name' => $ownerName,
                'active' => ($owner['archived'] ?? false) ? false : ($owner['active'] ?? true),
                'hubspot_created_at' => ! empty($owner['createdAt']) ? \Carbon\Carbon::parse($owner['createdAt']) : null,
                'hubspot_updated_at' => ! empty($owner['updatedAt']) ? \Carbon\Carbon::parse($owner['updatedAt']) : null,
            ]
        );

        return array_merge($owner, ['name' => $ownerName]);
    }

    private function syncLocalOwnerData(User $client, string $ownerId, array $owner): void
    {
        if (! Schema::hasColumn('users', 'hubspot_owner_id')) {
            return;
        }

        $client->forceFill(['hubspot_owner_id' => $ownerId])->save();
    }

    private function sendEmail(array $recipients, string $subject, string $content, array $ticket, array $owner, User $client): void
    {
        $ticketId = $ticket['id'] ?? 'N/A';
        $ownerLabel = $this->ownerName($owner) ?: ($owner['email'] ?? 'N/A');
        $body = implode("\n", [
            $content,
            '',
            '---',
            'Ticket HubSpot: ' . $ticketId,
            'Propietario HubSpot: ' . $ownerLabel . ' <' . ($owner['email'] ?? 'sin correo') . '>',
        ]);

        Mail::raw($body, function ($message) use ($recipients, $subject, $client) {
            $message->to($recipients)->subject($subject);

            if (! empty($client->email)) {
                $message->replyTo($client->email, $this->clientLabel($client));
            }
        });
    }

    private function buildTicketContent(User $client, User $requester, string $description, string $source, array $owner, bool $isTest): string
    {
        $lines = [
            $isTest ? 'MODO PRUEBA: solicitud generada desde el boton administrativo de demostracion.' : null,
            'Solicitud cargada desde App Sefar.',
            'Origen: ' . $source,
            'Fecha: ' . now()->format('Y-m-d H:i:s'),
            '',
            'Cliente: ' . $this->clientLabel($client),
            'Email cliente: ' . ($client->email ?: '-'),
            'Pasaporte cliente: ' . ($client->passport ?: '-'),
            'ID app cliente: ' . $client->id,
            'ID contacto HubSpot: ' . ($client->hs_id ?: '-'),
            '',
            'Solicitante: ' . ($requester->name ?: "Usuario #{$requester->id}") . " (ID {$requester->id})",
            'Email solicitante: ' . ($requester->email ?: '-'),
            '',
            'Propietario HubSpot: ' . ($this->ownerName($owner) ?: '-') . ' <' . ($owner['email'] ?? '-') . '>',
            '',
            'Detalle de la solicitud:',
            $description,
        ];

        return implode("\n", array_filter($lines, fn ($line) => $line !== null));
    }

    private function recipients(string $ownerEmail): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn ($email) => strtolower(trim($email)),
            array_merge(self::SUPPORT_EMAILS, [$ownerEmail])
        ))));
    }

    private function clientLabel(User $client): string
    {
        return $client->name ?: trim(($client->nombres ?? '') . ' ' . ($client->apellidos ?? '')) ?: "Cliente #{$client->id}";
    }

    private function ownerName(array $owner): string
    {
        $name = trim((string) ($owner['name'] ?? ''));

        if ($name !== '') {
            return $name;
        }

        return trim(((string) ($owner['firstName'] ?? '')) . ' ' . ((string) ($owner['lastName'] ?? '')));
    }
}
