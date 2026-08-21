<?php

namespace App\Services\Mcp;

use App\Models\Compras;
use App\Models\DocumentRequest;
use App\Models\Factura;
use App\Models\File as ClientFile;
use App\Models\Negocio;
use App\Models\Servicio;
use App\Models\Task;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class SefarMcpReadToolService
{
    private const TOOL_NAMES = [
        'resumen_cliente',
        'listar_negocios_cliente',
        'listar_compras_cliente',
        'listar_facturas_cliente',
        'listar_documentos_cliente',
        'listar_tareas_cliente',
        'buscar_servicios',
    ];

    public function supports(string $name): bool
    {
        return in_array($name, self::TOOL_NAMES, true);
    }

    public function tools(): array
    {
        return [
            [
                'name' => 'resumen_cliente',
                'description' => 'Resume datos operativos del cliente: ficha, conteos, ultimo negocio, compras, facturas, documentos, solicitudes y tareas sin recalcular COS.',
                'inputSchema' => $this->clientIdSchema(),
            ],
            [
                'name' => 'listar_negocios_cliente',
                'description' => 'Lista negocios locales asociados a un cliente, incluyendo estados operativos, servicio, IDs externos y fechas clave.',
                'inputSchema' => $this->clientListSchema(50, 10),
            ],
            [
                'name' => 'listar_compras_cliente',
                'description' => 'Lista compras, pagos y servicios adquiridos por un cliente desde la base local.',
                'inputSchema' => $this->clientListSchema(50, 20),
            ],
            [
                'name' => 'listar_facturas_cliente',
                'description' => 'Lista facturas locales de un cliente y sus compras asociadas.',
                'inputSchema' => $this->clientListSchema(50, 20),
            ],
            [
                'name' => 'listar_documentos_cliente',
                'description' => 'Lista documentos cargados y solicitudes documentales del cliente.',
                'inputSchema' => $this->clientListSchema(100, 25),
            ],
            [
                'name' => 'listar_tareas_cliente',
                'description' => 'Lista tareas comerciales u operativas asociadas al cliente como contacto.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => [
                            'type' => 'integer',
                            'minimum' => 1,
                        ],
                        'status' => [
                            'type' => 'string',
                            'description' => 'Filtro opcional: pending, in_progress, completed o canceled.',
                        ],
                        'limit' => [
                            'type' => 'integer',
                            'minimum' => 1,
                            'maximum' => 50,
                            'default' => 20,
                        ],
                    ],
                    'required' => ['id'],
                    'additionalProperties' => false,
                ],
            ],
            [
                'name' => 'buscar_servicios',
                'description' => 'Busca servicios/productos configurados en la plataforma por nombre, categoria, tipo o ID de HubSpot.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'Texto de busqueda opcional.',
                        ],
                        'solo_activos' => [
                            'type' => 'boolean',
                            'default' => false,
                        ],
                        'limit' => [
                            'type' => 'integer',
                            'minimum' => 1,
                            'maximum' => 50,
                            'default' => 20,
                        ],
                    ],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    public function call(string $name, array $arguments): array
    {
        return match ($name) {
            'resumen_cliente' => $this->resumenCliente($arguments),
            'listar_negocios_cliente' => $this->listarNegociosCliente($arguments),
            'listar_compras_cliente' => $this->listarComprasCliente($arguments),
            'listar_facturas_cliente' => $this->listarFacturasCliente($arguments),
            'listar_documentos_cliente' => $this->listarDocumentosCliente($arguments),
            'listar_tareas_cliente' => $this->listarTareasCliente($arguments),
            'buscar_servicios' => $this->buscarServicios($arguments),
            default => throw new RuntimeException("Herramienta no soportada: {$name}"),
        };
    }

    public function auditTarget(string $tool, array $arguments): array
    {
        return match ($tool) {
            'resumen_cliente' => [
                'type' => 'client_summary',
                'client_id' => $arguments['id'] ?? null,
                'may_write_database' => false,
            ],
            'listar_negocios_cliente' => [
                'type' => 'client_deals_read',
                'client_id' => $arguments['id'] ?? null,
                'limit' => $arguments['limit'] ?? 10,
                'may_write_database' => false,
            ],
            'listar_compras_cliente' => [
                'type' => 'client_purchases_read',
                'client_id' => $arguments['id'] ?? null,
                'limit' => $arguments['limit'] ?? 20,
                'may_write_database' => false,
            ],
            'listar_facturas_cliente' => [
                'type' => 'client_invoices_read',
                'client_id' => $arguments['id'] ?? null,
                'limit' => $arguments['limit'] ?? 20,
                'may_write_database' => false,
            ],
            'listar_documentos_cliente' => [
                'type' => 'client_documents_read',
                'client_id' => $arguments['id'] ?? null,
                'limit' => $arguments['limit'] ?? 25,
                'may_write_database' => false,
            ],
            'listar_tareas_cliente' => [
                'type' => 'client_tasks_read',
                'client_id' => $arguments['id'] ?? null,
                'status' => $arguments['status'] ?? null,
                'limit' => $arguments['limit'] ?? 20,
                'may_write_database' => false,
            ],
            'buscar_servicios' => [
                'type' => 'services_search',
                'query' => trim((string) ($arguments['query'] ?? '')),
                'limit' => $arguments['limit'] ?? 20,
                'may_write_database' => false,
            ],
            default => ['type' => 'unknown'],
        };
    }

    public function summarizeResult(string $tool, array $result): array
    {
        return match ($tool) {
            'resumen_cliente' => [
                'client_id' => $result['data']['client']['id'] ?? null,
                'counts' => $result['data']['counts'] ?? [],
            ],
            'listar_negocios_cliente',
            'listar_compras_cliente',
            'listar_facturas_cliente',
            'listar_tareas_cliente' => [
                'client_id' => $result['meta']['client_id'] ?? null,
                'results_count' => is_countable($result['data'] ?? null) ? count($result['data']) : null,
                'limit' => $result['meta']['limit'] ?? null,
            ],
            'listar_documentos_cliente' => [
                'client_id' => $result['meta']['client_id'] ?? null,
                'documentos_count' => is_countable($result['data']['documentos'] ?? null) ? count($result['data']['documentos']) : null,
                'solicitudes_count' => is_countable($result['data']['solicitudes'] ?? null) ? count($result['data']['solicitudes']) : null,
                'limit' => $result['meta']['limit'] ?? null,
            ],
            'buscar_servicios' => [
                'query' => $result['meta']['query'] ?? null,
                'results_count' => is_countable($result['data'] ?? null) ? count($result['data']) : null,
                'limit' => $result['meta']['limit'] ?? null,
            ],
            default => [
                'top_level_keys' => array_keys($result),
            ],
        };
    }

    private function resumenCliente(array $arguments): array
    {
        $client = $this->findClient($this->positiveId($arguments['id'] ?? null));

        return [
            'data' => [
                'client' => $this->serializeClient($client),
                'cos_cache' => $this->cosCacheSummary($client),
                'counts' => [
                    'negocios' => $this->safeCount('negocios', fn () => Negocio::where('user_id', $client->id)->count()),
                    'compras' => $this->safeCount('compras', fn () => Compras::where('id_user', $client->id)->count()),
                    'facturas' => $this->safeCount('facturas', fn () => Factura::where('id_cliente', $client->id)->count()),
                    'documentos' => $this->safeCount('files', fn () => ClientFile::where('user_id', $client->id)->count()),
                    'solicitudes_documentos' => $this->safeCount('document_requests', fn () => DocumentRequest::where('user_id', $client->id)->count()),
                    'tareas' => $this->safeCount('tasks', fn () => Task::where('contact_id', $client->id)->count()),
                    'tareas_abiertas' => $this->safeCount('tasks', fn () => Task::where('contact_id', $client->id)->whereIn('status', [Task::STATUS_PENDING, Task::STATUS_IN_PROGRESS])->count()),
                ],
                'latest' => [
                    'negocio' => $this->latestNegocio($client),
                    'compra' => $this->latestCompra($client),
                    'factura' => $this->latestFactura($client),
                    'documento' => $this->latestDocumento($client),
                    'solicitud_documento' => $this->latestSolicitudDocumento($client),
                    'tarea' => $this->latestTarea($client),
                ],
            ],
            'meta' => [
                'read_only' => true,
                'cos_recalculated' => false,
            ],
        ];
    }

    private function listarNegociosCliente(array $arguments): array
    {
        $client = $this->findClient($this->positiveId($arguments['id'] ?? null));
        $limit = $this->limit($arguments['limit'] ?? 10, 1, 50);

        if (! Schema::hasTable('negocios')) {
            return $this->emptyTableResponse($client, 'negocios', $limit);
        }

        $data = Negocio::query()
            ->select($this->tableColumns('negocios', $this->negocioColumns()))
            ->where('user_id', $client->id)
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (Negocio $negocio) => $this->serializeNegocio($negocio))
            ->values();

        return $this->listResponse($client, $data, 'negocios', $limit);
    }

    private function listarComprasCliente(array $arguments): array
    {
        $client = $this->findClient($this->positiveId($arguments['id'] ?? null));
        $limit = $this->limit($arguments['limit'] ?? 20, 1, 50);

        if (! Schema::hasTable('compras')) {
            return $this->emptyTableResponse($client, 'compras', $limit);
        }

        $data = Compras::query()
            ->select($this->tableColumns('compras', $this->compraColumns()))
            ->where('id_user', $client->id)
            ->with($this->comprasRelations())
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (Compras $compra) => $this->serializeCompra($compra))
            ->values();

        return $this->listResponse($client, $data, 'compras', $limit);
    }

    private function listarFacturasCliente(array $arguments): array
    {
        $client = $this->findClient($this->positiveId($arguments['id'] ?? null));
        $limit = $this->limit($arguments['limit'] ?? 20, 1, 50);

        if (! Schema::hasTable('facturas')) {
            return $this->emptyTableResponse($client, 'facturas', $limit);
        }

        $data = Factura::query()
            ->select($this->tableColumns('facturas', $this->facturaColumns()))
            ->where('id_cliente', $client->id)
            ->with($this->facturaRelations())
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (Factura $factura) => $this->serializeFactura($factura, true))
            ->values();

        return $this->listResponse($client, $data, 'facturas', $limit);
    }

    private function listarDocumentosCliente(array $arguments): array
    {
        $client = $this->findClient($this->positiveId($arguments['id'] ?? null));
        $limit = $this->limit($arguments['limit'] ?? 25, 1, 100);
        $documentos = collect();
        $solicitudes = collect();

        if (Schema::hasTable('files')) {
            $documentos = ClientFile::query()
                ->select($this->tableColumns('files', $this->fileColumns()))
                ->where('user_id', $client->id)
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get()
                ->map(fn (ClientFile $file) => $this->serializeFile($file))
                ->values();
        }

        if (Schema::hasTable('document_requests')) {
            $solicitudes = DocumentRequest::query()
                ->select($this->tableColumns('document_requests', $this->documentRequestColumns()))
                ->where('user_id', $client->id)
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get()
                ->map(fn (DocumentRequest $request) => $this->serializeDocumentRequest($request))
                ->values();
        }

        return [
            'data' => [
                'documentos' => $documentos,
                'solicitudes' => $solicitudes,
            ],
            'meta' => [
                'client_id' => $client->id,
                'limit' => $limit,
                'tables' => [
                    'files' => Schema::hasTable('files'),
                    'document_requests' => Schema::hasTable('document_requests'),
                ],
            ],
        ];
    }

    private function listarTareasCliente(array $arguments): array
    {
        $client = $this->findClient($this->positiveId($arguments['id'] ?? null));
        $limit = $this->limit($arguments['limit'] ?? 20, 1, 50);
        $status = trim((string) ($arguments['status'] ?? ''));

        if (! Schema::hasTable('tasks')) {
            return $this->emptyTableResponse($client, 'tasks', $limit);
        }

        $data = Task::query()
            ->select($this->tableColumns('tasks', $this->taskColumns()))
            ->where('contact_id', $client->id)
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->with($this->taskRelations())
            ->orderByDesc('due_date')
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (Task $task) => $this->serializeTask($task))
            ->values();

        $response = $this->listResponse($client, $data, 'tasks', $limit);
        $response['meta']['status'] = $status !== '' ? $status : null;

        return $response;
    }

    private function buscarServicios(array $arguments): array
    {
        $query = trim((string) ($arguments['query'] ?? ''));
        $limit = $this->limit($arguments['limit'] ?? 20, 1, 50);
        $soloActivos = (bool) ($arguments['solo_activos'] ?? false);

        if (! Schema::hasTable('servicios')) {
            return [
                'data' => [],
                'meta' => [
                    'table' => 'servicios',
                    'table_available' => false,
                    'limit' => $limit,
                ],
            ];
        }

        $data = Servicio::query()
            ->select($this->tableColumns('servicios', $this->servicioColumns()))
            ->when($soloActivos && Schema::hasColumn('servicios', 'activo'), fn ($builder) => $builder->where('activo', true))
            ->when($query !== '', function ($builder) use ($query) {
                $builder->where(function ($nested) use ($query) {
                    foreach (['id', 'id_hubspot', 'nombre', 'categoria', 'tipo'] as $column) {
                        if (Schema::hasColumn('servicios', $column)) {
                            $nested->orWhere($column, 'like', "%{$query}%");
                        }
                    }
                });
            })
            ->orderBy(Schema::hasColumn('servicios', 'orden') ? 'orden' : 'nombre')
            ->limit($limit)
            ->get()
            ->map(fn (Servicio $servicio) => $this->serializeServicio($servicio))
            ->values();

        return [
            'data' => $data,
            'meta' => [
                'query' => $query,
                'solo_activos' => $soloActivos,
                'limit' => $limit,
                'table' => 'servicios',
            ],
        ];
    }

    private function serializeClient(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'nombres' => $user->nombres,
            'apellidos' => $user->apellidos,
            'email' => $user->email,
            'phone' => $user->phone,
            'passport' => $user->passport,
            'servicio' => $user->servicio,
            'pay' => $user->pay,
            'contrato' => $user->contrato,
            'cosready' => (bool) $user->cosready,
            'arraycos_expire' => optional($user->arraycos_expire)->toIso8601String(),
            'hs_id' => $user->hs_id,
            'tl_id' => $user->tl_id,
            'monday_id' => $user->monday_id,
            'created_at' => optional($user->created_at)->toIso8601String(),
            'updated_at' => optional($user->updated_at)->toIso8601String(),
        ];
    }

    private function serializeNegocio(Negocio $negocio): array
    {
        return [
            'id' => $negocio->id,
            'user_id' => $negocio->user_id,
            'hubspot_id' => $negocio->hubspot_id,
            'teamleader_id' => $negocio->teamleader_id,
            'numero' => $negocio->numero,
            'cliente_numero' => $negocio->cliente__numero,
            'nombre_cliente' => $negocio->nombre_cliente,
            'apellidos_del_cliente' => $negocio->apellidos_del_cliente,
            'no_pasaporte' => $negocio->no__pasaporte,
            'servicio_solicitado' => $negocio->servicio_solicitado,
            'servicio_solicitado2' => $negocio->servicio_solicitado2,
            'estatus_proceso' => $negocio->estatus_proceso,
            'estatus_genealogia' => $negocio->estatus__genealogia,
            'codigo_de_proceso' => $negocio->codigo_de_proceso,
            'proyecto' => $negocio->proyecto,
            'proyecto_tl' => $negocio->proyecto_tl,
            'no_de_proyecto_tl' => $negocio->no_de_proyecto_tl,
            'analista_de_ventas' => $negocio->analista_de_ventas,
            'supervisor_de_ventas' => $negocio->supervisor_de_ventas,
            'representante_legal' => $negocio->representante_legal,
            'abogado_asesor' => $negocio->abogado___asesor__clonada_,
            'fecha_de_aceptacion' => $this->dateValue($negocio->fecha_de_aceptacion),
            'fecha_de_cobro' => $this->dateValue($negocio->fecha_de_cobro),
            'fecha_de_resolucion' => $this->dateValue($negocio->fecha_de_resolucion),
            'fecha_de_rechazo' => $this->dateValue($negocio->fecha_de_rechazo),
            'fecha_en_la_que_se_anadio' => $this->dateValue($negocio->fecha_en_la_que_se_anadio),
            'motivo_del_rechazo' => $negocio->motivo_del_rechazo__explicacion_,
            'created_at' => optional($negocio->created_at)->toIso8601String(),
            'updated_at' => optional($negocio->updated_at)->toIso8601String(),
        ];
    }

    private function serializeCompra(Compras $compra): array
    {
        return [
            'id' => $compra->id,
            'id_user' => $compra->id_user,
            'servicio_id' => $this->attr($compra, 'servicio_id'),
            'servicio_hs_id' => $compra->servicio_hs_id,
            'servicio' => $compra->relationLoaded('servicio') && $compra->servicio ? $this->serializeServicio($compra->servicio) : null,
            'source' => $this->attr($compra, 'source'),
            'descripcion' => $compra->descripcion,
            'pagado' => $compra->pagado,
            'monto' => $compra->monto,
            'montooriginal' => $this->attr($compra, 'montooriginal'),
            'porcentajedescuento' => $this->attr($compra, 'porcentajedescuento'),
            'cuponaplicado' => $this->attr($compra, 'cuponaplicado'),
            'hash_factura' => $compra->hash_factura,
            'deal_id' => $this->attr($compra, 'deal_id'),
            'phasenum' => $this->attr($compra, 'phasenum'),
            'paid_at' => $this->dateTimeValue($this->attr($compra, 'paid_at')),
            'metadata' => $this->attr($compra, 'metadata'),
            'factura' => $compra->relationLoaded('factura') && $compra->factura ? $this->serializeFactura($compra->factura) : null,
            'created_at' => optional($compra->created_at)->toIso8601String(),
            'updated_at' => optional($compra->updated_at)->toIso8601String(),
        ];
    }

    private function serializeFactura(Factura $factura, bool $includeCompras = false): array
    {
        $data = [
            'id' => $factura->id,
            'id_cliente' => $factura->id_cliente,
            'hash_factura' => $factura->hash_factura,
            'met' => $factura->met,
            'idcus' => $factura->idcus,
            'idcharge' => $factura->idcharge,
            'created_at' => optional($factura->created_at)->toIso8601String(),
            'updated_at' => optional($factura->updated_at)->toIso8601String(),
        ];

        if ($includeCompras) {
            $data['compras'] = $factura->relationLoaded('compras')
                ? $factura->compras->map(fn (Compras $compra) => $this->serializeCompra($compra))->values()
                : [];
        }

        return $data;
    }

    private function serializeFile(ClientFile $file): array
    {
        return [
            'id' => $file->id,
            'file' => $file->file,
            'location' => $file->location,
            'tipo' => $file->tipo,
            'propietario' => $file->propietario,
            'IDCliente' => $file->IDCliente,
            'notas' => $file->notas,
            'IDPersona' => $this->attr($file, 'IDPersona'),
            'IDPersonaNew' => $this->attr($file, 'IDPersonaNew'),
            'user_id' => $file->user_id,
            'created_at' => optional($file->created_at)->toIso8601String(),
            'updated_at' => optional($file->updated_at)->toIso8601String(),
        ];
    }

    private function serializeDocumentRequest(DocumentRequest $request): array
    {
        return [
            'id' => $request->id,
            'user_id' => $request->user_id,
            'requested_by' => $request->requested_by,
            'document_name' => $request->document_name,
            'document_type' => $request->document_type,
            'status' => $request->status,
            'status_changed_at' => $this->dateTimeValue($this->attr($request, 'status_changed_at')),
            'file_path' => $request->file_path,
            'no_document_button_at' => $this->dateTimeValue($this->attr($request, 'no_document_button_at')),
            'created_at' => optional($request->created_at)->toIso8601String(),
            'updated_at' => optional($request->updated_at)->toIso8601String(),
        ];
    }

    private function serializeTask(Task $task): array
    {
        return [
            'id' => $task->id,
            'assignee' => $task->relationLoaded('assignee') && $task->assignee ? $this->serializeInternalUser($task->assignee) : null,
            'contact_id' => $task->contact_id,
            'title' => $task->title,
            'description' => $task->description,
            'due_date' => $this->dateValue($task->due_date),
            'status' => $task->status,
            'contact_methods' => $this->attr($task, 'contact_methods'),
            'customer_responded' => $this->attr($task, 'customer_responded'),
            'call_effective' => $task->call_effective,
            'reason_no_effective' => $task->reason_no_effective,
            'interest_level' => $task->interest_level,
            'sale_status' => $this->attr($task, 'sale_status'),
            'sale_status_label' => method_exists($task, 'saleStatusLabel') ? $task->saleStatusLabel() : null,
            'sales_tags' => $this->attr($task, 'sales_tags'),
            'reason_no_interest' => $task->reason_no_interest,
            'product_of_interest' => $task->product_of_interest,
            'contact_proof' => $this->attr($task, 'contact_proof'),
            'follow_up_date' => $this->dateValue($task->follow_up_date),
            'created_by_user_id' => $task->created_by_user_id,
            'task_pool_list_name' => $this->attr($task, 'task_pool_list_name'),
            'created_at' => optional($task->created_at)->toIso8601String(),
            'updated_at' => optional($task->updated_at)->toIso8601String(),
        ];
    }

    private function serializeServicio(Servicio $servicio): array
    {
        return [
            'id' => $servicio->id,
            'id_hubspot' => $servicio->id_hubspot,
            'nombre' => $servicio->nombre,
            'precio' => $servicio->precio,
            'tipov' => $this->attr($servicio, 'tipov'),
            'categoria' => $this->attr($servicio, 'categoria'),
            'tipo' => $this->attr($servicio, 'tipo'),
            'activo' => $this->attr($servicio, 'activo'),
            'visible_cliente' => $this->attr($servicio, 'visible_cliente'),
            'moneda' => $this->attr($servicio, 'moneda'),
            'duracion_minutos' => $this->attr($servicio, 'duracion_minutos'),
            'requiere_agenda' => $this->attr($servicio, 'requiere_agenda'),
            'orden' => $this->attr($servicio, 'orden'),
            'hubspot_pipeline_id' => $this->attr($servicio, 'hubspot_pipeline_id'),
            'hubspot_stage_id' => $this->attr($servicio, 'hubspot_stage_id'),
            'created_at' => optional($servicio->created_at)->toIso8601String(),
            'updated_at' => optional($servicio->updated_at)->toIso8601String(),
        ];
    }

    private function clientIdSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => [
                    'type' => 'integer',
                    'minimum' => 1,
                ],
            ],
            'required' => ['id'],
            'additionalProperties' => false,
        ];
    }

    private function clientListSchema(int $max, int $default): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => [
                    'type' => 'integer',
                    'minimum' => 1,
                ],
                'limit' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => $max,
                    'default' => $default,
                ],
            ],
            'required' => ['id'],
            'additionalProperties' => false,
        ];
    }

    private function findClient(int $id): User
    {
        $user = User::find($id);

        if (! $user || ! $user->hasRole('Cliente')) {
            throw new RuntimeException('Cliente no encontrado.');
        }

        return $user;
    }

    private function tableColumns(string $table, array $columns): array
    {
        return array_values(array_filter($columns, fn (string $column) => Schema::hasColumn($table, $column)));
    }

    private function negocioColumns(): array
    {
        return [
            'id',
            'user_id',
            'hubspot_id',
            'teamleader_id',
            'numero',
            'cliente__numero',
            'nombre_cliente',
            'apellidos_del_cliente',
            'no__pasaporte',
            'servicio_solicitado',
            'servicio_solicitado2',
            'estatus_proceso',
            'estatus__genealogia',
            'codigo_de_proceso',
            'proyecto',
            'proyecto_tl',
            'no_de_proyecto_tl',
            'analista_de_ventas',
            'supervisor_de_ventas',
            'representante_legal',
            'abogado___asesor__clonada_',
            'fecha_de_aceptacion',
            'fecha_de_cobro',
            'fecha_de_resolucion',
            'fecha_de_rechazo',
            'fecha_en_la_que_se_anadio',
            'motivo_del_rechazo__explicacion_',
            'created_at',
            'updated_at',
        ];
    }

    private function compraColumns(): array
    {
        return [
            'id',
            'id_user',
            'servicio_id',
            'source',
            'servicio_hs_id',
            'descripcion',
            'pagado',
            'monto',
            'montooriginal',
            'porcentajedescuento',
            'cuponaplicado',
            'hash_factura',
            'deal_id',
            'phasenum',
            'metadata',
            'paid_at',
            'created_at',
            'updated_at',
        ];
    }

    private function facturaColumns(): array
    {
        return [
            'id',
            'id_cliente',
            'hash_factura',
            'met',
            'idcus',
            'idcharge',
            'created_at',
            'updated_at',
        ];
    }

    private function fileColumns(): array
    {
        return [
            'id',
            'file',
            'location',
            'tipo',
            'propietario',
            'IDCliente',
            'notas',
            'IDPersona',
            'IDPersonaNew',
            'user_id',
            'created_at',
            'updated_at',
        ];
    }

    private function documentRequestColumns(): array
    {
        return [
            'id',
            'user_id',
            'requested_by',
            'document_name',
            'document_type',
            'status',
            'status_changed_at',
            'file_path',
            'no_document_button_at',
            'created_at',
            'updated_at',
        ];
    }

    private function taskColumns(): array
    {
        return [
            'id',
            'user_id',
            'contact_id',
            'title',
            'description',
            'due_date',
            'status',
            'contact_methods',
            'customer_responded',
            'call_effective',
            'reason_no_effective',
            'interest_level',
            'sale_status',
            'sales_tags',
            'reason_no_interest',
            'product_of_interest',
            'contact_proof',
            'follow_up_date',
            'created_by_user_id',
            'task_pool_list_name',
            'created_at',
            'updated_at',
        ];
    }

    private function servicioColumns(): array
    {
        return [
            'id',
            'id_hubspot',
            'nombre',
            'precio',
            'tipov',
            'categoria',
            'tipo',
            'activo',
            'visible_cliente',
            'moneda',
            'duracion_minutos',
            'requiere_agenda',
            'orden',
            'hubspot_pipeline_id',
            'hubspot_stage_id',
            'created_at',
            'updated_at',
        ];
    }

    private function comprasRelations(): array
    {
        $relations = [];

        if (Schema::hasTable('servicios') && Schema::hasColumn('compras', 'servicio_id')) {
            $relations[] = 'servicio';
        }

        if (Schema::hasTable('facturas') && Schema::hasColumn('compras', 'hash_factura')) {
            $relations[] = 'factura';
        }

        return $relations;
    }

    private function facturaRelations(): array
    {
        if (! Schema::hasTable('compras') || ! Schema::hasColumn('compras', 'hash_factura')) {
            return [];
        }

        return ['compras' => function ($query) {
            $query->select($this->tableColumns('compras', $this->compraColumns()));
        }];
    }

    private function taskRelations(): array
    {
        return Schema::hasTable('users') && Schema::hasColumn('tasks', 'user_id') ? ['assignee'] : [];
    }

    private function latestNegocio(User $client): ?array
    {
        if (! Schema::hasTable('negocios')) {
            return null;
        }

        $record = Negocio::query()
            ->select($this->tableColumns('negocios', $this->negocioColumns()))
            ->where('user_id', $client->id)
            ->orderByDesc('updated_at')
            ->first();

        return $record ? $this->serializeNegocio($record) : null;
    }

    private function latestCompra(User $client): ?array
    {
        if (! Schema::hasTable('compras')) {
            return null;
        }

        $record = Compras::query()
            ->select($this->tableColumns('compras', $this->compraColumns()))
            ->where('id_user', $client->id)
            ->orderByDesc('created_at')
            ->first();

        return $record ? $this->serializeCompra($record) : null;
    }

    private function latestFactura(User $client): ?array
    {
        if (! Schema::hasTable('facturas')) {
            return null;
        }

        $record = Factura::query()
            ->select($this->tableColumns('facturas', $this->facturaColumns()))
            ->where('id_cliente', $client->id)
            ->orderByDesc('created_at')
            ->first();

        return $record ? $this->serializeFactura($record) : null;
    }

    private function latestDocumento(User $client): ?array
    {
        if (! Schema::hasTable('files')) {
            return null;
        }

        $record = ClientFile::query()
            ->select($this->tableColumns('files', $this->fileColumns()))
            ->where('user_id', $client->id)
            ->orderByDesc('created_at')
            ->first();

        return $record ? $this->serializeFile($record) : null;
    }

    private function latestSolicitudDocumento(User $client): ?array
    {
        if (! Schema::hasTable('document_requests')) {
            return null;
        }

        $record = DocumentRequest::query()
            ->select($this->tableColumns('document_requests', $this->documentRequestColumns()))
            ->where('user_id', $client->id)
            ->orderByDesc('created_at')
            ->first();

        return $record ? $this->serializeDocumentRequest($record) : null;
    }

    private function latestTarea(User $client): ?array
    {
        if (! Schema::hasTable('tasks')) {
            return null;
        }

        $record = Task::query()
            ->select($this->tableColumns('tasks', $this->taskColumns()))
            ->where('contact_id', $client->id)
            ->orderByDesc('updated_at')
            ->first();

        return $record ? $this->serializeTask($record) : null;
    }

    private function cosCacheSummary(User $client): array
    {
        $cos = is_array($client->arraycos) ? $client->arraycos : [];

        return [
            'ready' => (bool) $client->cosready,
            'expires_at' => optional($client->arraycos_expire)->toIso8601String(),
            'is_fresh' => $client->arraycos_expire ? $client->arraycos_expire->isFuture() : false,
            'items_count' => count($cos),
            'current_steps' => collect($cos)->map(function ($item) {
                return [
                    'servicio' => $item['servicio'] ?? null,
                    'currentStepName' => $item['currentStepName'] ?? null,
                    'currentStepGen' => $item['currentStepGen'] ?? null,
                    'currentStepJur' => $item['currentStepJur'] ?? null,
                    'progressPercentageGen' => $item['progressPercentageGen'] ?? null,
                    'progressPercentageJur' => $item['progressPercentageJur'] ?? null,
                ];
            })->values(),
        ];
    }

    private function emptyTableResponse(User $client, string $table, int $limit): array
    {
        return [
            'data' => [],
            'meta' => [
                'client_id' => $client->id,
                'limit' => $limit,
                'table' => $table,
                'table_available' => false,
            ],
        ];
    }

    private function listResponse(User $client, $data, string $table, int $limit): array
    {
        return [
            'data' => $data,
            'meta' => [
                'client_id' => $client->id,
                'limit' => $limit,
                'table' => $table,
                'table_available' => true,
            ],
        ];
    }

    private function safeCount(string $table, callable $callback): int
    {
        return Schema::hasTable($table) ? (int) $callback() : 0;
    }

    private function serializeInternalUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];
    }

    private function attr(Model $model, string $key): mixed
    {
        return array_key_exists($key, $model->getAttributes()) ? $model->getAttribute($key) : null;
    }

    private function positiveId(mixed $value): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT);

        if ($id === false || $id < 1) {
            throw new RuntimeException('id debe ser un entero positivo.');
        }

        return (int) $id;
    }

    private function limit(mixed $value, int $min, int $max): int
    {
        return max($min, min($max, (int) $value));
    }

    private function dateValue(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return (string) $value;
    }

    private function dateTimeValue(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        return (string) $value;
    }
}
