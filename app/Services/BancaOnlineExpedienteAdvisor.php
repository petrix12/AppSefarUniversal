<?php

namespace App\Services;

use App\Models\Compras;
use App\Models\BancaOnlineDocumentRule;
use App\Models\DocumentRequest;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class BancaOnlineExpedienteAdvisor
{
    public function __construct(
        private BancaOnlineCatalog $catalog,
        private BancaOnlineFlow $flow,
        private ClientStageResolver $clientStages,
        private BancaOnlineCosContext $cosContext
    ) {
    }

    public function forUser(?User $user, string $countrySlug = 'espana', ?string $selectedCaseStatus = null, array $plans = [], ?string $entryPoint = null): array
    {
        $countrySlug = $this->catalog->normalizeCountry($countrySlug);

        if (! $user) {
            return $this->emptyContext($countrySlug);
        }

        $cosContext = $this->cosContext->forUser($user);
        $documents = $this->documentsFor($user);
        $purchases = $this->purchasesFor($user);
        $clientStage = $this->clientStages->resolve($user, [
            'has_cos' => (bool) ($cosContext['available'] ?? false),
            'has_pending_document' => $documents['pending']->isNotEmpty() || $documents['missing']->isNotEmpty(),
            'has_pending_professional_activation' => $purchases['pending']->isNotEmpty(),
            'has_active_professional_service' => $purchases['paid']->isNotEmpty(),
        ]);
        $detectedCaseStatus = $this->detectedCaseStatus($clientStage, $cosContext, $documents);
        $recommendedCaseStatus = $detectedCaseStatus
            ?: $this->flow->normalizeCaseStatus($selectedCaseStatus)
            ?: $this->flow->defaultCaseStatusForStage($clientStage);
        $plans = $plans ?: $this->catalog->plansForCountry($countrySlug);
        $recommendation = $this->flow->recommendation($recommendedCaseStatus, $plans, $clientStage);
        $documentSupport = $this->documentSupport($countrySlug, $documents, $recommendedCaseStatus, $entryPoint);
        $nextAction = $this->nextAction($user, $clientStage, $documents, $purchases, $cosContext, $documentSupport);

        return [
            'visible' => true,
            'country_slug' => $countrySlug,
            'client_stage' => $clientStage,
            'profile' => $clientStage['profile'] ?? 'candidate',
            'stage' => $clientStage['stage'] ?? null,
            'stage_label' => $clientStage['label'] ?? 'Expediente',
            'summary' => $this->summary($clientStage, $cosContext, $documents, $purchases),
            'cos' => $cosContext,
            'documents' => $this->publicDocuments($documents),
            'purchases' => $this->publicPurchases($purchases),
            'responsibilities' => $this->responsibilities($user, $clientStage, $documents, $purchases, $cosContext, $documentSupport),
            'next_action' => $nextAction,
            'document_support' => $documentSupport,
            'detected_case_status' => $detectedCaseStatus,
            'recommended_case_status' => $recommendedCaseStatus,
            'recommendation' => [
                'plan_slug' => $recommendation['plan_slug'] ?? null,
                'plan_title' => $recommendation['plan_title'] ?? null,
                'reason' => $recommendation['reason'] ?? null,
            ],
        ];
    }

    public function publicLookupContext(?User $user, array $context): array
    {
        if (! $user || ! ($context['visible'] ?? false)) {
            return ['visible' => false, 'has_private_context' => false];
        }

        if (! $this->canSeePrivateContext($user)) {
            return [
                'visible' => false,
                'has_private_context' => true,
                'stage_label' => $context['stage_label'] ?? 'Expediente encontrado',
                'summary' => 'Encontramos un expediente asociado a este correo. Por seguridad, el detalle se muestra al iniciar sesion.',
            ];
        }

        return [
            'visible' => true,
            'has_private_context' => true,
            'stage_label' => $context['stage_label'] ?? 'Expediente',
            'summary' => $context['summary'] ?? null,
            'next_action' => $context['next_action'] ?? null,
            'documents' => [
                'pending_count' => $context['documents']['pending_count'] ?? 0,
                'missing_count' => $context['documents']['missing_count'] ?? 0,
            ],
        ];
    }

    private function documentsFor(User $user): array
    {
        $requests = collect();

        if ($user->exists && $this->schemaHasTable('document_requests')) {
            $requests = DocumentRequest::query()
                ->where('user_id', $user->id)
                ->latest('updated_at')
                ->limit(40)
                ->get();
        }

        $mapped = $requests
            ->map(fn (DocumentRequest $request) => $this->publicDocumentRequest($request))
            ->filter()
            ->values();

        return [
            'all' => $mapped,
            'pending' => $mapped->whereIn('status', ['en_espera_cliente', 'rechazada'])->values(),
            'missing' => $mapped->where('status', 'no_documento')->values(),
            'received' => $mapped->whereIn('status', ['resuelto', 'aprobada'])->values(),
        ];
    }

    private function purchasesFor(User $user): array
    {
        $purchases = collect();

        if ($user->exists && $this->schemaHasTable('compras') && $this->schemaHasColumn('compras', 'source')) {
            $purchases = Compras::query()
                ->with('servicio')
                ->where('id_user', $user->id)
                ->where('source', $this->catalog->source())
                ->latest('updated_at')
                ->limit(40)
                ->get();
        }

        return [
            'all' => $purchases,
            'pending' => $purchases->filter(fn (Compras $purchase) => (int) $purchase->pagado !== 1)->values(),
            'paid' => $purchases->filter(fn (Compras $purchase) => (int) $purchase->pagado === 1)->values(),
        ];
    }

    private function publicDocumentRequest(DocumentRequest $request): ?array
    {
        $name = $this->cleanText($request->document_name);

        if ($name === '') {
            return null;
        }

        $status = (string) ($request->status ?? 'en_espera_cliente');

        return [
            'id' => $request->id,
            'name' => $name,
            'type' => $request->document_type,
            'type_label' => $request->document_type === 'juridico' ? 'Juridico' : 'Genealogico',
            'status' => $status,
            'status_label' => $this->documentStatusLabel($status),
            'owner' => $this->documentOwner($status),
            'can_upload' => in_array($status, ['en_espera_cliente', 'rechazada'], true),
        ];
    }

    private function publicDocuments(array $documents): array
    {
        return [
            'pending_count' => $documents['pending']->count(),
            'missing_count' => $documents['missing']->count(),
            'received_count' => $documents['received']->count(),
            'pending' => $documents['pending']->take(4)->values()->all(),
            'missing' => $documents['missing']->take(4)->values()->all(),
            'received' => $documents['received']->take(3)->values()->all(),
        ];
    }

    private function publicPurchases(array $purchases): array
    {
        return [
            'pending_count' => $purchases['pending']->count(),
            'paid_count' => $purchases['paid']->count(),
            'pending' => $purchases['pending']->take(3)->map(fn (Compras $purchase) => $this->publicPurchase($purchase))->values()->all(),
            'paid' => $purchases['paid']->take(3)->map(fn (Compras $purchase) => $this->publicPurchase($purchase))->values()->all(),
        ];
    }

    private function publicPurchase(Compras $purchase): array
    {
        $metadata = $purchase->metadata ?? [];
        $token = $metadata['checkout_token'] ?? null;

        return [
            'id' => $purchase->id,
            'title' => $metadata['package_title'] ?? $purchase->servicio?->nombre ?? $purchase->descripcion ?? 'Servicio Banca Online',
            'plan' => $metadata['plan_title'] ?? $metadata['plan_slug'] ?? null,
            'status' => (int) $purchase->pagado === 1 ? 'paid' : 'pending_payment',
            'status_label' => (int) $purchase->pagado === 1 ? 'Pagado' : 'Pago pendiente',
            'amount' => (float) ($purchase->monto ?? 0),
            'payment_url' => $token ? $this->safeRoute('banca-online.payment', $token) : null,
        ];
    }

    private function detectedCaseStatus(array $clientStage, array $cosContext, array $documents): ?string
    {
        if ($documents['missing']->isNotEmpty() || $documents['pending']->isNotEmpty()) {
            return 'requirement_received';
        }

        $text = Str::lower(Str::ascii(collect($cosContext['entries'] ?? [])
            ->map(fn (array $entry) => ($entry['current_step'] ?? '') . ' ' . ($entry['service'] ?? ''))
            ->implode(' ')));

        if (Str::contains($text, ['deneg', 'rechaz'])) {
            return 'denied';
        }

        if (Str::contains($text, ['requer', 'subsan'])) {
            return 'requirement_received';
        }

        if (Str::contains($text, ['present', 'formaliz', 'expediente en plataforma', 'radic'])) {
            return 'application_submitted';
        }

        if (Str::contains($text, ['revision', 'preanalisis', 'analisis', 'estudio'])) {
            return 'under_review';
        }

        return ($clientStage['profile'] ?? null) === 'represented' ? 'represented_active' : null;
    }

    private function nextAction(User $user, array $clientStage, array $documents, array $purchases, array $cosContext, array $documentSupport): array
    {
        if ($purchases['pending']->isNotEmpty()) {
            $purchase = $this->publicPurchase($purchases['pending']->first());

            return [
                'type' => 'payment',
                'owner' => 'client',
                'title' => 'Continuar pago pendiente',
                'description' => 'Tienes una activacion de Banca Online guardada. Puedes retomarla sin perder el progreso.',
                'label' => 'Continuar pago',
                'url' => $purchase['payment_url'],
                'tone' => 'warning',
            ];
        }

        if ($documents['pending']->isNotEmpty()) {
            return [
                'type' => 'document',
                'owner' => 'client',
                'title' => 'Completar documentacion requerida',
                'description' => 'Hay documentos solicitados para que el expediente pueda avanzar sin retrasos.',
                'label' => 'Ver solicitudes',
                'url' => $this->safeRoute('clientes.tree'),
                'tone' => 'warning',
            ];
        }

        if ($documents['missing']->isNotEmpty() && ($documentSupport['available'] ?? false)) {
            return [
                'type' => 'document_support',
                'owner' => 'client',
                'title' => 'Resolver documento no disponible',
                'description' => 'Podemos ayudarte a buscar o sustituir estrategicamente el soporte documental faltante.',
                'label' => $documentSupport['label'] ?? 'Ver apoyo documental',
                'url' => $documentSupport['url'] ?? null,
                'tone' => 'info',
            ];
        }

        $pay = (int) ($clientStage['signals']['pay'] ?? ($user->pay ?? 0));

        if (($clientStage['profile'] ?? null) === 'candidate') {
            if (in_array($pay, [1, 3], true)) {
                return [
                    'type' => 'getinfo',
                    'owner' => 'client',
                    'title' => 'Completar informacion del expediente',
                    'description' => 'Despues del pago base, necesitamos tus datos para preparar la siguiente fase.',
                    'label' => 'Completar informacion',
                    'url' => $this->safeRoute('clientes.getinfo'),
                    'tone' => 'info',
                ];
            }

            if ($pay === 2 && (int) ($user->contrato ?? 0) !== 1) {
                return [
                    'type' => 'contract',
                    'owner' => 'client',
                    'title' => 'Firmar contrato pendiente',
                    'description' => 'La representacion queda formalizada cuando el contrato esta firmado.',
                    'label' => 'Firmar contrato',
                    'url' => $this->safeRoute('cliente.contrato'),
                    'tone' => 'warning',
                ];
            }
        }

        if (($cosContext['available'] ?? false) && ($clientStage['profile'] ?? null) === 'represented') {
            return [
                'type' => 'cos',
                'owner' => 'client',
                'title' => 'Revisar estatus publicado',
                'description' => 'Tu expediente tiene informacion de avance disponible en el canal de estatus.',
                'label' => 'Ver estatus',
                'url' => $this->safeRoute('clientes.tree'),
                'tone' => 'success',
            ];
        }

        return [
            'type' => 'strategy',
            'owner' => 'client',
            'title' => 'Elegir estrategia profesional',
            'description' => 'Selecciona el alcance que mejor corresponde al momento actual del expediente.',
            'label' => 'Revisar estrategias',
            'url' => null,
            'tone' => 'info',
        ];
    }

    private function responsibilities(User $user, array $clientStage, array $documents, array $purchases, array $cosContext, array $documentSupport): array
    {
        $client = collect();
        $sefar = collect();

        if ($documents['pending']->isNotEmpty()) {
            $client->push('Subir los documentos solicitados o indicar si alguno no esta disponible.');
        }

        if ($documents['missing']->isNotEmpty() && ($documentSupport['available'] ?? false)) {
            $client->push('Revisar la alternativa documental recomendada para los soportes faltantes.');
            $sefar->push('Evaluar la via documental o juridica para resolver el faltante.');
        }

        if ($purchases['pending']->isNotEmpty()) {
            $client->push('Completar el pago pendiente de la activacion seleccionada.');
        }

        if (($clientStage['profile'] ?? null) === 'candidate') {
            $pay = (int) ($clientStage['signals']['pay'] ?? ($user->pay ?? 0));

            if ($pay === 0) {
                $client->push('Regularizar el registro inicial cuando corresponda.');
            } elseif (in_array($pay, [1, 3], true)) {
                $client->push('Completar el formulario de informacion del expediente.');
            } elseif ($pay === 2 && (int) ($user->contrato ?? 0) !== 1) {
                $client->push('Firmar el contrato pendiente.');
            }
        }

        foreach (collect($cosContext['entries'] ?? [])->take(2) as $entry) {
            $sefar->push('Mantener actualizado el avance de ' . ($entry['service'] ?? 'tu proceso') . ' en etapa ' . ($entry['current_step'] ?? 'en revision') . '.');
        }

        if ($purchases['paid']->isNotEmpty()) {
            $sefar->push('Incorporar la activacion pagada al expediente operativo.');
        }

        if ($client->isEmpty()) {
            $client->push('Revisar la recomendacion y confirmar si quieres incorporar un nuevo alcance.');
        }

        if ($sefar->isEmpty()) {
            $sefar->push('Revisar el contexto del expediente y validar el alcance antes de avanzar.');
        }

        return [
            'client' => $client->unique()->values()->take(4)->all(),
            'sefar' => $sefar->unique()->values()->take(4)->all(),
        ];
    }

    private function documentSupport(string $countrySlug, array $documents, ?string $caseStatus = null, ?string $entryPoint = null): array
    {
        $target = $documents['missing']->first() ?: $documents['pending']->first();

        if (! $target) {
            return ['available' => false];
        }

        $fallbackPlanSlug = ($target['type'] ?? null) === 'juridico' ? 'administrativo' : 'solicitud-estrategica';
        $rule = $this->matchingDocumentRule($countrySlug, $target, $fallbackPlanSlug);
        $planSlug = $rule?->recommended_plan_slug ?: $rule?->plan_slug ?: $fallbackPlanSlug;

        if (! $this->catalog->planForCountry($countrySlug, $planSlug)) {
            $planSlug = array_key_first($this->catalog->plansForCountry($countrySlug)) ?: null;
        }

        if (! $planSlug) {
            return ['available' => false];
        }

        return [
            'available' => true,
            'rule_id' => $rule?->id,
            'document_name' => $target['name'],
            'document_type' => $target['type'],
            'plan_slug' => $planSlug,
            'recommended_service_id' => $rule?->recommended_service_id,
            'recommended_service_name' => $rule?->recommendedService?->nombre,
            'label' => $rule?->client_label ?: (($target['type'] ?? null) === 'juridico'
                ? 'Ver apoyo juridico-documental'
                : 'Ver apoyo genealogico-documental'),
            'reason' => $rule?->client_explanation
                ?: 'Este documento puede requerir busqueda, sustitucion o defensa documental dentro del expediente.',
            'url' => $this->safeRoute('banca-online.configure.country', [
                'country' => $countrySlug,
                'plan' => $planSlug,
                'status' => $caseStatus ?: 'requirement_received',
                'entry_point' => $entryPoint ?: 'cos',
            ]),
        ];
    }

    private function matchingDocumentRule(string $countrySlug, array $document, string $fallbackPlanSlug): ?BancaOnlineDocumentRule
    {
        if (! $this->schemaHasTable('banca_online_document_rules')) {
            return null;
        }

        $documentName = $this->normalizedText($document['name'] ?? '');
        $documentType = (string) ($document['type'] ?? 'otro');

        if ($documentName === '') {
            return null;
        }

        return BancaOnlineDocumentRule::query()
            ->with('recommendedService')
            ->where('active', true)
            ->where('client_visible', true)
            ->where(function ($query) use ($countrySlug) {
                $query->whereNull('country_slug')
                    ->orWhere('country_slug', $countrySlug);
            })
            ->where(function ($query) use ($fallbackPlanSlug) {
                $query->whereNull('plan_slug')
                    ->orWhere('plan_slug', $fallbackPlanSlug);
            })
            ->whereIn('document_type', [$documentType, 'otro'])
            ->orderBy('priority')
            ->orderBy('sort_order')
            ->get()
            ->first(function (BancaOnlineDocumentRule $rule) use ($documentName) {
                $ruleName = $this->normalizedText($rule->document_name);

                if ($ruleName !== '' && ($ruleName === $documentName || str_contains($documentName, $ruleName) || str_contains($ruleName, $documentName))) {
                    return true;
                }

                foreach (($rule->match_keywords ?? []) as $keyword) {
                    $keyword = $this->normalizedText($keyword);

                    if ($keyword !== '' && str_contains($documentName, $keyword)) {
                        return true;
                    }
                }

                return false;
            });
    }

    private function summary(array $clientStage, array $cosContext, array $documents, array $purchases): string
    {
        if ($documents['pending']->isNotEmpty()) {
            return 'Tu expediente tiene documentacion requerida pendiente por completar.';
        }

        if ($documents['missing']->isNotEmpty()) {
            return 'Hay documentos marcados como no disponibles; conviene revisar una alternativa documental.';
        }

        if ($purchases['pending']->isNotEmpty()) {
            return 'Tienes una activacion de Banca Online pendiente de pago.';
        }

        if (($cosContext['summary'] ?? null) && ($cosContext['available'] ?? false)) {
            return $cosContext['summary'];
        }

        return ($clientStage['profile'] ?? null) === 'represented'
            ? 'Tu expediente esta activo y puede recibir una nueva estrategia profesional.'
            : 'Tu registro esta asociado a Sefar y puede avanzar por fases.';
    }

    private function documentStatusLabel(string $status): string
    {
        return match ($status) {
            'en_espera_cliente' => 'Pendiente por subir',
            'resuelto' => 'Recibido, en revision',
            'no_documento' => 'No disponible',
            'aprobada' => 'Aprobado',
            'rechazada' => 'Requiere correccion',
            default => 'En revision',
        };
    }

    private function documentOwner(string $status): string
    {
        return match ($status) {
            'en_espera_cliente', 'rechazada', 'no_documento' => 'client',
            default => 'sefar',
        };
    }

    private function cleanText($value): string
    {
        return trim(strip_tags((string) $value));
    }

    private function normalizedText($value): string
    {
        return Str::lower(Str::ascii($this->cleanText($value)));
    }

    private function safeRoute(string $name, mixed $parameters = []): ?string
    {
        try {
            return Route::has($name) ? route($name, $parameters) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function schemaHasTable(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function schemaHasColumn(string $table, string $column): bool
    {
        try {
            return Schema::hasColumn($table, $column);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function canSeePrivateContext(User $user): bool
    {
        $viewer = auth()->user();

        if (! $viewer) {
            return false;
        }

        if ((int) $viewer->id === (int) $user->id) {
            return true;
        }

        try {
            return $viewer->hasAnyRole(['Admin', 'Administrador', 'Super Admin'])
                || $viewer->can('administrador');
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function emptyContext(string $countrySlug): array
    {
        return [
            'visible' => false,
            'country_slug' => $countrySlug,
            'profile' => 'visitor',
            'stage' => 'visitor',
            'stage_label' => 'Visitante',
            'summary' => 'Explora las estrategias disponibles y usa tu correo principal al activar.',
            'cos' => $this->cosContext->forUser(null),
            'documents' => [
                'pending_count' => 0,
                'missing_count' => 0,
                'received_count' => 0,
                'pending' => [],
                'missing' => [],
                'received' => [],
            ],
            'purchases' => [
                'pending_count' => 0,
                'paid_count' => 0,
                'pending' => [],
                'paid' => [],
            ],
            'responsibilities' => [
                'client' => [],
                'sefar' => [],
            ],
            'next_action' => null,
            'document_support' => ['available' => false],
            'detected_case_status' => null,
            'recommended_case_status' => null,
            'recommendation' => null,
        ];
    }
}
