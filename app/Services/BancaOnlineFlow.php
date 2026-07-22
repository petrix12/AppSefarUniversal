<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BancaOnlineFlow
{
    public const ENTRY_POINTS = ['external', 'internal', 'admin_quote', 'cos'];

    public function caseStatusOptions(): array
    {
        return [
            'not_started' => [
                'label' => 'Todavia no he iniciado mi expediente',
                'summary' => 'Necesito conocer la ruta adecuada antes de presentar una solicitud.',
                'recommended_plan' => 'solicitud-estrategica',
                'reason' => 'Primero conviene disenar la estrategia y definir el alcance profesional antes de presentar cualquier solicitud.',
            ],
            'paid_pending_info' => [
                'label' => 'Ya pague y debo completar informacion',
                'summary' => 'Estoy en el proceso base y aun debo completar el formulario de informacion.',
                'recommended_plan' => 'solicitud-estrategica',
                'reason' => 'Antes de activar nuevos alcances, conviene completar la informacion requerida para formalizar el expediente.',
            ],
            'pending_contract' => [
                'label' => 'Tengo el contrato pendiente',
                'summary' => 'Ya complete informacion, pero todavia debo firmar el contrato.',
                'recommended_plan' => 'solicitud-estrategica',
                'reason' => 'La prioridad operativa es formalizar la relacion mediante contrato antes de incorporar nuevos alcances.',
            ],
            'application_submitted' => [
                'label' => 'Ya presentamos mi solicitud',
                'summary' => 'Mi expediente fue presentado y necesito seguimiento o refuerzo administrativo.',
                'recommended_plan' => 'administrativo',
                'reason' => 'Cuando la solicitud ya fue presentada, la actuacion recomendada suele enfocarse en seguimiento, subsanaciones y gestion administrativa.',
            ],
            'under_review' => [
                'label' => 'Mi expediente esta siendo estudiado',
                'summary' => 'La administracion esta revisando mi expediente.',
                'recommended_plan' => 'administrativo',
                'reason' => 'En fase de estudio conviene reforzar el seguimiento administrativo y preparar respuestas ante posibles requerimientos.',
            ],
            'requirement_received' => [
                'label' => 'He recibido un requerimiento',
                'summary' => 'Necesito responder una solicitud documental o juridica.',
                'recommended_plan' => 'administrativo',
                'reason' => 'Un requerimiento exige una actuacion administrativa ordenada, con documentos y argumentos alineados al expediente.',
            ],
            'denied' => [
                'label' => 'Mi solicitud fue denegada',
                'summary' => 'Necesito evaluar una estrategia posterior a la denegacion.',
                'recommended_plan' => 'judicial',
                'reason' => 'Cuando existe una denegacion, la siguiente actuacion suele requerir evaluacion de via judicial o contenciosa.',
            ],
            'represented_active' => [
                'label' => 'Ya soy representado de Sefar Universal',
                'summary' => 'Quiero incorporar un nuevo alcance o revisar el siguiente paso de mi expediente.',
                'recommended_plan' => 'administrativo',
                'reason' => 'Para representados, la recomendacion debe partir del expediente, COS, compras previas y procesos activos.',
            ],
            'other_process' => [
                'label' => 'Quiero iniciar otro proceso',
                'summary' => 'Ya tengo o tuve un proceso y quiero cotizar otro alcance.',
                'recommended_plan' => 'solicitud-estrategica',
                'reason' => 'Para un nuevo proceso conviene iniciar con una revision estrategica del alcance, documentos y viabilidad.',
            ],
        ];
    }

    public function normalizeCaseStatus(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $key = str_replace('-', '_', Str::slug(Str::ascii($value), '_'));
        $aliases = [
            'no_he_iniciado' => 'not_started',
            'todavia_no_he_iniciado' => 'not_started',
            'ya_pague' => 'paid_pending_info',
            'pago_pendiente_info' => 'paid_pending_info',
            'contrato_pendiente' => 'pending_contract',
            'ya_presentamos' => 'application_submitted',
            'presentado' => 'application_submitted',
            'estudio' => 'under_review',
            'requerimiento' => 'requirement_received',
            'denegado' => 'denied',
            'representado' => 'represented_active',
            'otro_proceso' => 'other_process',
        ];

        $key = $aliases[$key] ?? $key;

        return array_key_exists($key, $this->caseStatusOptions()) ? $key : null;
    }

    public function defaultCaseStatusForStage(array $clientStage): string
    {
        return match ($clientStage['stage'] ?? null) {
            'candidate_paid_pending_info' => 'paid_pending_info',
            'candidate_pending_contract' => 'pending_contract',
            'represented_initial',
            'represented_with_cos',
            'represented_pending_document',
            'represented_pending_professional_activation',
            'represented_service_active' => 'represented_active',
            default => 'not_started',
        };
    }

    public function entryPoint(Request $request, ?User $authenticatedUser = null): string
    {
        $raw = trim((string) ($request->input('entry_point') ?: $request->query('entry_point') ?: $request->query('entry')));
        $entryPoint = str_replace('-', '_', Str::slug(Str::ascii($raw), '_'));

        if (in_array($entryPoint, self::ENTRY_POINTS, true)) {
            return $entryPoint;
        }

        if ($request->filled('quote_id') || $request->filled('cotizacion_id')) {
            return 'admin_quote';
        }

        $from = Str::lower((string) ($request->query('from') ?: $request->input('from')));

        if ($from === 'cos' || $request->boolean('cos')) {
            return 'cos';
        }

        return $authenticatedUser ? 'internal' : 'external';
    }

    public function quoteContext(Request $request, ?User $authenticatedUser = null): array
    {
        $context = [];

        foreach (['quote_id', 'cotizacion_id', 'quote_source', 'quote_reference', 'process_id', 'deal_id'] as $key) {
            $value = trim((string) ($request->input($key) ?: $request->query($key)));

            if ($value !== '') {
                $context[$key] = Str::limit($value, 160, '');
            }
        }

        if ($authenticatedUser) {
            $context['authenticated_user_id'] = $authenticatedUser->id;
        }

        return $context;
    }

    public function recommendation(?string $caseStatus, array $plans, ?array $clientStage = null): array
    {
        $caseStatus = $caseStatus ? $this->normalizeCaseStatus($caseStatus) : null;
        $caseStatus ??= $clientStage ? $this->defaultCaseStatusForStage($clientStage) : 'not_started';

        $option = $this->caseStatusOptions()[$caseStatus] ?? $this->caseStatusOptions()['not_started'];
        $recommendedPlan = (string) ($option['recommended_plan'] ?? '');

        if (! array_key_exists($recommendedPlan, $plans)) {
            $recommendedPlan = array_key_first($plans) ?: '';
        }

        return [
            'case_status' => $caseStatus,
            'case_status_label' => $option['label'] ?? $caseStatus,
            'plan_slug' => $recommendedPlan,
            'plan_title' => $plans[$recommendedPlan]['public_title'] ?? $plans[$recommendedPlan]['title'] ?? null,
            'reason' => $option['reason'] ?? null,
            'matched' => $recommendedPlan !== '',
        ];
    }

    public function rationale(?string $caseStatus, array $plan, array $recommendation, ?string $planSlug = null): array
    {
        $caseStatus = $this->normalizeCaseStatus($caseStatus) ?? 'not_started';
        $option = $this->caseStatusOptions()[$caseStatus] ?? $this->caseStatusOptions()['not_started'];
        $isRecommendedPlan = $planSlug && ($recommendation['plan_slug'] ?? null) === $planSlug;
        $planTitle = $plan['public_title'] ?? $plan['title'] ?? $recommendation['plan_title'] ?? 'Estrategia recomendada';

        $objectives = [
            'not_started' => 'Definir la estrategia antes de presentar cualquier solicitud, reduciendo improvisaciones y omisiones documentales.',
            'paid_pending_info' => 'Ordenar la informacion pendiente para que el expediente pueda avanzar con base clara.',
            'pending_contract' => 'Formalizar la relacion profesional antes de incorporar nuevas actuaciones al expediente.',
            'application_submitted' => 'Reforzar el expediente presentado con seguimiento, impulso y respuesta administrativa.',
            'under_review' => 'Acompanar la fase de estudio y preparar respuestas ante posibles solicitudes de la administracion.',
            'requirement_received' => 'Responder el requerimiento con documentos y argumentos alineados al expediente.',
            'denied' => 'Evaluar la viabilidad de una actuacion posterior y preparar una estrategia procesal solida.',
            'represented_active' => 'Incorporar el siguiente alcance profesional al expediente con contexto operativo.',
            'other_process' => 'Abrir un nuevo proceso con alcance, documentos y viabilidad definidos desde el inicio.',
        ];

        return [
            'title' => $planTitle,
            'case_status' => $caseStatus,
            'case_status_label' => $option['label'] ?? $caseStatus,
            'reason' => $isRecommendedPlan
                ? ($recommendation['reason'] ?? $option['reason'] ?? 'Esta estrategia corresponde al contexto indicado para tu expediente.')
                : 'Esta estrategia puede seleccionarse si buscas ese alcance concreto. Segun el contexto indicado, tambien revisaremos que el alcance elegido se ajuste al expediente antes de avanzar.',
            'objective' => $objectives[$caseStatus] ?? $objectives['not_started'],
            'professionals' => [
                'Equipo de coordinacion del expediente',
                'Area genealogica y documental cuando aplique',
                'Area juridica para estrategia, revision y escritos',
            ],
            'expected_result' => 'Que el expediente avance con una actuacion profesional concreta, trazable y vinculada al alcance seleccionado.',
            'documents' => [
                'Documentos de identidad y datos del solicitante o representado',
                'Antecedentes y documentos ya disponibles del expediente',
                'Soportes genealogicos, administrativos o juridicos segun la ruta',
            ],
            'afterwards' => 'Al seleccionar el alcance veras los servicios incluidos, el importe de activacion y el siguiente paso operativo.',
        ];
    }
}
