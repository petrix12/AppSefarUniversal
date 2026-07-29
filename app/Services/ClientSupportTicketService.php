<?php

namespace App\Services;

use App\Models\HubspotOwner;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class ClientSupportTicketService
{
    public const TOPICS = [
        'temas_administrativos' => 'Temas administrativos (Facturas y presupuestos)',
        'solicitudes_legales' => 'Solicitudes legales',
        'actualizacion_estatus' => 'Actualización de mi estatus',
        'preguntas_proceso' => 'Preguntas sobre mi proceso',
        'acuerdo_pago' => 'Solicitar un acuerdo de pago',
        'otros' => 'Otros',
    ];

    private const SUPPORT_EMAILS = [
        'sistemasccs@sefarvzla.com',
    ];

    private const HUBSPOT_INBOX_EMAIL = 'info@sefarvzla.com';

    public function __construct(private HubspotService $hubspot)
    {
    }

    public static function topics(): array
    {
        return self::TOPICS;
    }

    public function create(User $client, User $requester, string $description, string $topic, string $source, bool $isTest = false): array
    {
        $description = trim($description);
        $topicLabel = $this->topicLabel($topic);

        if ($description === '') {
            throw new \InvalidArgumentException('La solicitud no puede estar vacia.');
        }

        $contact = $this->resolveContact($client);
        $contactId = (string) ($contact['id'] ?? '');
        $contactProperties = $contact['properties'] ?? [];
        $ownerId = $contactId !== ''
            ? trim((string) ($contactProperties['hubspot_owner_id'] ?? ''))
            : '';
        $owner = [];

        if ($ownerId !== '') {
            try {
                $owner = $this->resolveOwner($ownerId);
            } catch (\Throwable $e) {
                Log::warning('No se pudo resolver owner HubSpot para solicitud de soporte; se omitira copia al asesor.', [
                    'client_id' => $client->id,
                    'hubspot_owner_id' => $ownerId,
                    'error' => $e->getMessage(),
                ]);

                $ownerId = '';
            }
        }

        $ownerEmail = strtolower(trim((string) ($owner['email'] ?? '')));

        if ($ownerId !== '') {
            $this->syncLocalOwnerData($client, $ownerId, $owner);
        }

        $subject = ($isTest ? '[PRUEBA] ' : '') . '[' . $topicLabel . '] Solicitud de soporte del cliente: ' . $this->clientLabel($client);
        $content = $this->buildTicketContent($client, $requester, $description, $topicLabel, $source, $owner, $isTest, $contactId);
        $ticket = null;
        $ticketError = null;

        if ($contactId !== '') {
            try {
                $ticket = $this->createHubspotTicket($subject, $content, $contactId);
            } catch (\Throwable $e) {
                $ticketError = $e->getMessage();

                Log::warning('No se pudo crear ticket HubSpot por API; se usara correo a info como fallback.', [
                    'client_id' => $client->id,
                    'hubspot_contact_id' => $contactId,
                    'error' => $ticketError,
                ]);
            }
        } else {
            $ticketError = 'No se encontro contacto HubSpot para asociar ticket.';
        }

        $recipients = $this->recipients($ownerEmail);
        $copyHubspotInbox = empty($ticket['id']);
        $this->sendEmail(
            $recipients,
            $subject,
            $content,
            $owner,
            $client,
            $ticket ?? [],
            $copyHubspotInbox,
            $ticketError,
            [
                'requester' => $requester,
                'description' => $description,
                'topic' => $topicLabel,
                'source' => $source,
                'is_test' => $isTest,
                'contact_id' => $contactId,
            ]
        );

        Log::info('Solicitud de soporte enviada desde App Sefar.', [
            'client_id' => $client->id,
            'requester_id' => $requester->id,
            'hubspot_contact_id' => $contactId,
            'hubspot_owner_id' => $ownerId,
            'hubspot_ticket_id' => $ticket['id'] ?? null,
            'hubspot_ticket_error' => $ticketError,
            'support_topic' => $topicLabel,
            'recipients' => $recipients,
            'hubspot_inbox_email' => $copyHubspotInbox ? self::HUBSPOT_INBOX_EMAIL : null,
            'source' => $source,
            'is_test' => $isTest,
        ]);

        return [
            'ticket_id' => $ticket['id'] ?? null,
            'owner_email' => $ownerEmail ?: null,
            'owner_id' => $ownerId ?: null,
            'recipients' => array_values(array_unique(array_merge($copyHubspotInbox ? [self::HUBSPOT_INBOX_EMAIL] : [], $recipients))),
            'ticket_error' => $ticketError,
            'used_hubspot_inbox_fallback' => $copyHubspotInbox,
            'support_topic' => $topicLabel,
        ];
    }

    private function createHubspotTicket(string $subject, string $content, string $contactId): array
    {
        $configuredPipeline = env('HUBSPOT_SUPPORT_TICKET_PIPELINE');
        $configuredStage = env('HUBSPOT_SUPPORT_TICKET_STAGE');
        $pipeline = $this->hubspot->getTicketPipelineStage($configuredPipeline, $configuredStage);

        if (
            trim((string) $configuredPipeline) !== ''
            && trim((string) $configuredStage) !== ''
            && (
                (string) $configuredPipeline !== (string) ($pipeline['hs_pipeline'] ?? '')
                || (string) $configuredStage !== (string) ($pipeline['hs_pipeline_stage'] ?? '')
            )
        ) {
            Log::warning('Configuracion de pipeline/etapa HubSpot invalida; se usara una etapa activa valida.', [
                'configured_pipeline' => $configuredPipeline,
                'configured_stage' => $configuredStage,
                'used_pipeline' => $pipeline['hs_pipeline'] ?? null,
                'used_stage' => $pipeline['hs_pipeline_stage'] ?? null,
            ]);
        }

        return $this->hubspot->createTicket([
            'subject' => $subject,
            'content' => $content,
            'hs_pipeline' => $pipeline['hs_pipeline'] ?? null,
            'hs_pipeline_stage' => $pipeline['hs_pipeline_stage'] ?? null,
            'hs_ticket_priority' => env('HUBSPOT_SUPPORT_TICKET_PRIORITY', 'MEDIUM'),
        ], $contactId);
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
            try {
                $contact = $this->hubspot->searchContactOwnerByEmail($client->email);
            } catch (\Throwable $e) {
                Log::warning('No se pudo buscar contacto HubSpot por email para solicitud de soporte.', [
                    'client_id' => $client->id,
                    'email' => $client->email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (! $contact && ! empty($client->passport)) {
            try {
                $passportContact = $this->hubspot->searchContactByPassport($client->passport);
                if (! empty($passportContact['id'])) {
                    $contact = $this->hubspot->getContactOwnerById((string) $passportContact['id']);
                }
            } catch (\Throwable $e) {
                Log::warning('No se pudo buscar contacto HubSpot por pasaporte para solicitud de soporte.', [
                    'client_id' => $client->id,
                    'passport' => $client->passport,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (! $contact || empty($contact['id'])) {
            return [];
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

    private function sendEmail(
        array $recipients,
        string $subject,
        string $content,
        array $owner,
        User $client,
        array $ticket,
        bool $copyHubspotInbox,
        ?string $ticketError,
        array $context
    ): void
    {
        $clientEmail = strtolower(trim((string) $client->email));

        if (! filter_var($clientEmail, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('El cliente no tiene un correo valido para enviar la solicitud.');
        }

        $to = $copyHubspotInbox ? self::HUBSPOT_INBOX_EMAIL : self::SUPPORT_EMAILS[0];
        $cc = $copyHubspotInbox ? $recipients : array_values(array_filter($recipients, fn ($email) => $email !== $to));

        Mail::send('emails.support_ticket_request', [
            'client' => $client,
            'clientName' => $this->clientLabel($client),
            'clientEmail' => $clientEmail,
            'owner' => $owner,
            'ownerName' => $this->ownerName($owner),
            'ticket' => $ticket,
            'ticketError' => $ticketError,
            'copyHubspotInbox' => $copyHubspotInbox,
            'hubspotInboxEmail' => self::HUBSPOT_INBOX_EMAIL,
            'content' => $content,
            'requester' => $context['requester'] ?? null,
            'description' => $context['description'] ?? '',
            'topic' => $context['topic'] ?? '',
            'source' => $context['source'] ?? '',
            'isTest' => (bool) ($context['is_test'] ?? false),
            'contactId' => $context['contact_id'] ?? null,
        ], function ($message) use ($to, $cc, $subject, $client, $clientEmail) {
            $message
                ->from($clientEmail, $this->clientLabel($client))
                ->replyTo($clientEmail, $this->clientLabel($client))
                ->to($to)
                ->cc($cc)
                ->subject($subject);
        });
    }

    private function buildTicketContent(User $client, User $requester, string $description, string $topic, string $source, array $owner, bool $isTest, ?string $contactId): string
    {
        $lines = [
            $isTest ? 'MODO PRUEBA: solicitud generada desde el boton administrativo de demostracion.' : null,
            'Solicitud cargada desde App Sefar.',
            'Origen: ' . $source,
            'Tema: ' . $topic,
            'Fecha: ' . now()->format('Y-m-d H:i:s'),
            '',
            'Cliente: ' . $this->clientLabel($client),
            'Email cliente: ' . ($client->email ?: '-'),
            'Pasaporte cliente: ' . ($client->passport ?: '-'),
            'ID app cliente: ' . $client->id,
            'ID contacto HubSpot: ' . ($contactId ?: '-'),
            '',
            'Solicitante: ' . ($requester->name ?: "Usuario #{$requester->id}") . " (ID {$requester->id})",
            'Email solicitante: ' . ($requester->email ?: '-'),
            '',
            'Propietario HubSpot: ' . ($this->ownerName($owner) ?: '-') . ' <' . ($owner['email'] ?? '-') . '>',
            $contactId ? null : 'Nota: no se encontro contacto asignado en HubSpot; se envio solo por correo a soporte.',
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

    private function topicLabel(string $topic): string
    {
        $topic = trim($topic);

        if (isset(self::TOPICS[$topic])) {
            return self::TOPICS[$topic];
        }

        if (in_array($topic, self::TOPICS, true)) {
            return $topic;
        }

        throw new \InvalidArgumentException('Selecciona un tema valido para la solicitud.');
    }
}
