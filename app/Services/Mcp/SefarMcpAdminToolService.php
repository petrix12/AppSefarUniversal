<?php

namespace App\Services\Mcp;

use App\Models\TlContact;
use App\Models\TlInvoice;
use App\Models\User;
use App\Services\HubspotService;
use App\Services\TeamleaderClientHistoryService;
use App\Services\TeamleaderService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use RuntimeException;

class SefarMcpAdminToolService
{
    private const TOOLS = ['revisar_cliente_integral', 'actualizar_cliente_app'];

    public function __construct(
        private readonly SefarMcpReadToolService $appData,
        private readonly HubspotService $hubspot,
        private readonly TeamleaderService $teamleader,
        private readonly TeamleaderClientHistoryService $teamleaderHistory,
    ) {
    }

    public function supports(string $name): bool
    {
        return in_array($name, self::TOOLS, true);
    }

    public function tools(): array
    {
        return [
            [
                'name' => 'revisar_cliente_integral',
                'description' => 'Consulta, para un cliente, todos los datos relacionados que la app tiene disponibles en App Sefar, HubSpot y Teamleader. Es exclusiva para administradores; no incluye contrasenas ni descarga el contenido de archivos.',
                'inputSchema' => $this->clientIdSchema(),
            ],
            [
                'name' => 'actualizar_cliente_app',
                'description' => 'Actualiza campos permitidos del perfil del cliente en App Sefar. Solo administradores; cada cambio queda en la auditoria de usuario y MCP. No modifica HubSpot ni Teamleader.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer', 'minimum' => 1],
                        'changes' => [
                            'type' => 'object',
                            'description' => 'Campos del perfil que se desean actualizar. Valores nulos se permiten para limpiar phone o passport.',
                            'properties' => [
                                'name' => ['type' => 'string', 'maxLength' => 255],
                                'nombres' => ['type' => 'string', 'maxLength' => 255],
                                'apellidos' => ['type' => 'string', 'maxLength' => 255],
                                'email' => ['type' => 'string', 'format' => 'email', 'maxLength' => 255],
                                'phone' => ['type' => ['string', 'null'], 'maxLength' => 255],
                                'passport' => ['type' => ['string', 'null'], 'maxLength' => 255],
                                'servicio' => ['type' => ['string', 'null'], 'maxLength' => 255],
                                'pay' => ['type' => 'integer', 'minimum' => 0],
                                'contrato' => ['type' => 'boolean'],
                            ],
                            'minProperties' => 1,
                            'additionalProperties' => false,
                        ],
                    ],
                    'required' => ['id', 'changes'],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    public function call(string $name, array $arguments): array
    {
        return match ($name) {
            'revisar_cliente_integral' => $this->revisarClienteIntegral($arguments),
            'actualizar_cliente_app' => $this->actualizarClienteApp($arguments),
            default => throw new RuntimeException("Herramienta no soportada: {$name}"),
        };
    }

    public function auditTarget(string $name, array $arguments): array
    {
        return [
            'type' => $name,
            'client_id' => $arguments['id'] ?? null,
            'fields' => array_keys(is_array($arguments['changes'] ?? null) ? $arguments['changes'] : []),
            'may_write_database' => $name === 'actualizar_cliente_app',
        ];
    }

    public function auditArguments(string $name, array $arguments): array
    {
        if ($name === 'actualizar_cliente_app') {
            return [
                'id' => $arguments['id'] ?? null,
                'fields' => array_keys(is_array($arguments['changes'] ?? null) ? $arguments['changes'] : []),
            ];
        }

        return $arguments;
    }

    public function summarizeResult(string $name, array $result): array
    {
        if ($name === 'actualizar_cliente_app') {
            return [
                'client_id' => $result['data']['id'] ?? null,
                'updated_fields' => $result['meta']['updated_fields'] ?? [],
            ];
        }

        return [
            'client_id' => $result['data']['client_id'] ?? null,
            'sources' => array_keys($result['data']['sources'] ?? []),
            'source_status' => array_map(
                fn (array $source) => $source['status'] ?? 'unknown',
                $result['data']['sources'] ?? [],
            ),
        ];
    }

    public function isAdministrator(User $user): bool
    {
        return $user->hasRole('Administrador');
    }

    private function revisarClienteIntegral(array $arguments): array
    {
        $client = $this->findClient($arguments['id'] ?? null);
        $app = [
            'profile' => $this->appData->call('resumen_cliente', ['id' => $client->id]),
            'cos_cache' => [
                'ready' => (bool) $client->cosready,
                'expires_at' => $client->arraycos_expire?->toIso8601String(),
                'items' => $client->arraycos ?? [],
            ],
            'businesses' => $this->appData->call('listar_negocios_cliente', ['id' => $client->id, 'limit' => 50]),
            'purchases' => $this->appData->call('listar_compras_cliente', ['id' => $client->id, 'limit' => 50]),
            'invoices' => $this->appData->call('listar_facturas_cliente', ['id' => $client->id, 'limit' => 50]),
            'documents' => $this->appData->call('listar_documentos_cliente', ['id' => $client->id, 'limit' => 100]),
            'tasks' => $this->appData->call('listar_tareas_cliente', ['id' => $client->id, 'limit' => 50]),
        ];

        $sources = [
            'app' => ['status' => 'ok', 'data' => $app],
            'hubspot' => $this->hubspotSnapshot($client),
            'teamleader' => $this->teamleaderSnapshot($client),
        ];

        return [
            'data' => [
                'client_id' => $client->id,
                'sources' => $sources,
            ],
            'meta' => [
                'read_only' => true,
                'external_sources_refreshed' => ['hubspot' => true, 'teamleader' => true],
                'app_records_are_live' => true,
                'passwords_included' => false,
                'file_contents_downloaded' => false,
                'record_limits' => ['businesses' => 50, 'purchases' => 50, 'invoices' => 50, 'documents' => 100, 'tasks' => 50],
                'app_records_truncated' => $this->appRecordsTruncated($app),
            ],
        ];
    }

    private function hubspotSnapshot(User $client): array
    {
        $id = trim((string) $client->hs_id);
        if ($id === '') {
            return ['status' => 'unlinked', 'reason' => 'El perfil de la app no tiene hs_id.'];
        }

        try {
            $contact = $this->hubspot->getContactById($id, true);
            $deals = $this->hubspot->getDealsByContactId($id, true);
            $files = $this->hubspot->getContactFileFields($id);
            $form = $this->hubspot->formulario001ForContact($id);

            return [
                'status' => 'ok',
                'contact' => $contact,
                'deals' => $deals,
                'files' => $files,
                'formulario_001' => $form,
            ];
        } catch (\Throwable $exception) {
            return ['status' => 'error', 'message' => $exception->getMessage()];
        }
    }

    private function teamleaderSnapshot(User $client): array
    {
        $id = trim((string) $client->tl_id);
        if ($id === '') {
            return ['status' => 'unlinked', 'reason' => 'El perfil de la app no tiene tl_id.'];
        }

        try {
            $contact = $this->teamleader->getContactById($id);
            $deals = $this->allContactPages(fn (int $page, int $size) => $this->teamleader->listDealsByContactId($id, $page, $size));
            $projects = $this->allContactPages(fn (int $page, int $size) => $this->teamleader->listProjectsByContactId($id, $page, $size));

            $deals = array_map(function (array $deal) {
                $dealId = (string) ($deal['id'] ?? '');
                return $dealId !== '' ? [
                    'summary' => $deal,
                    'details' => $this->optional(fn () => $this->teamleader->getDealById($dealId)),
                    'files' => $this->optional(fn () => $this->allContactPages(fn (int $page, int $size) => $this->teamleader->listFiles('deal', $dealId, $page, $size))),
                ] : ['summary' => $deal];
            }, $deals);

            $projects = array_map(function (array $project) {
                $projectId = (string) ($project['id'] ?? '');
                return $projectId !== '' ? [
                    'summary' => $project,
                    'details' => $this->optional(fn () => $this->teamleader->getProjectDetails($projectId)),
                    'files' => $this->optional(fn () => $this->allContactPages(fn (int $page, int $size) => $this->teamleader->listFiles('project', $projectId, $page, $size))),
                ] : ['summary' => $project];
            }, $projects);

            $mirror = $this->teamleaderHistory->for($client);
            $localContact = TlContact::query()->find($id);
            $localInvoices = Schema::hasTable('tl_invoices')
                ? TlInvoice::query()->where('customer_id', $id)->get()
                : collect();

            return [
                'status' => 'ok',
                'contact' => $contact,
                'deals' => $deals,
                'projects' => $projects,
                'local_mirror' => [
                    'contact' => $localContact,
                    'invoices' => $localInvoices,
                    'payment_history' => $mirror,
                    'invoice_freshness' => 'Copia local de la ultima sincronizacion; no se lista el universo completo de facturas remoto.',
                ],
                'files' => $this->optional(fn () => $this->teamleader->listFiles('contact', $id)),
            ];
        } catch (\Throwable $exception) {
            return ['status' => 'error', 'message' => $exception->getMessage()];
        }
    }

    private function allContactPages(callable $fetch): array
    {
        $items = [];
        $page = 1;
        do {
            $response = $fetch($page, 100);
            $batch = $response['data'] ?? [];
            if (! is_array($batch)) {
                throw new RuntimeException('Teamleader devolvió una lista de registros no válida.');
            }
            $items = array_merge($items, $batch);
            $total = (int) ($response['meta']['page']['total'] ?? count($items));
            $page++;
        } while (count($batch) === 100 && count($items) < $total && count($items) < 500);

        return $items;
    }

    private function optional(callable $callback): array
    {
        try {
            return ['status' => 'ok', 'data' => $callback()];
        } catch (\Throwable $exception) {
            return ['status' => 'error', 'message' => $exception->getMessage()];
        }
    }

    private function appRecordsTruncated(array $app): array
    {
        $counts = data_get($app, 'profile.data.counts', []);

        return [
            'businesses' => ($counts['negocios'] ?? 0) > 50,
            'purchases' => ($counts['compras'] ?? 0) > 50,
            'invoices' => ($counts['facturas'] ?? 0) > 50,
            'documents' => ($counts['documentos'] ?? 0) > 100 || ($counts['solicitudes_documentos'] ?? 0) > 100,
            'tasks' => ($counts['tareas'] ?? 0) > 50,
        ];
    }

    private function actualizarClienteApp(array $arguments): array
    {
        $client = $this->findClient($arguments['id'] ?? null);
        $changes = $arguments['changes'] ?? null;
        if (! is_array($changes) || $changes === []) {
            throw new RuntimeException('changes debe contener al menos un campo permitido.');
        }

        $allowed = ['name', 'nombres', 'apellidos', 'email', 'phone', 'passport', 'servicio', 'pay', 'contrato'];
        $unknown = array_diff(array_keys($changes), $allowed);
        if ($unknown !== []) {
            throw new RuntimeException('Campos no permitidos: '.implode(', ', $unknown));
        }

        $rules = [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'nombres' => ['sometimes', 'required', 'string', 'max:255'],
            'apellidos' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($client->id)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:255'],
            'passport' => ['sometimes', 'nullable', 'string', 'max:255'],
            'servicio' => ['sometimes', 'nullable', 'string', 'max:255'],
            'pay' => ['sometimes', 'required', 'integer', 'min:0'],
            'contrato' => ['sometimes', 'required', 'boolean'],
        ];
        $validated = Validator::make($changes, $rules)->validate();

        $client->fill($validated);
        $client->save();
        $client->refresh();

        return [
            'data' => [
                'id' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
                'updated_at' => $client->updated_at?->toIso8601String(),
            ],
            'meta' => [
                'updated_fields' => array_keys($validated),
                'saved_in' => 'app_sefar',
                'hubspot_updated' => false,
                'teamleader_updated' => false,
            ],
        ];
    }

    private function findClient(mixed $id): User
    {
        if (! is_numeric($id) || (int) $id < 1) {
            throw new RuntimeException('id debe ser un entero positivo.');
        }

        $client = User::query()->find((int) $id);
        if (! $client || ! $client->hasRole('Cliente')) {
            throw new RuntimeException('Cliente no encontrado.');
        }

        return $client;
    }

    private function clientIdSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => ['id' => ['type' => 'integer', 'minimum' => 1]],
            'required' => ['id'],
            'additionalProperties' => false,
        ];
    }
}
