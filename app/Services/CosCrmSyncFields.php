<?php

namespace App\Services;

/** Inputs of the COS calculation, not its derived progress or access permissions. */
class CosCrmSyncFields
{
    public const HUBSPOT_CONTACT = [
        'firstname', 'lastname', 'email', 'phone', 'numero_de_pasaporte',
        'servicio_solicitado', 'retirado',
    ];

    // Verified against the property definitions of the production portal on 2026-11-16.
    public const HUBSPOT_DEAL = [
        'dealname', 'dealstage', 'servicio_solicitado', 'servicio_solicitado2', 'codigo_de_proceso',
        'n2__enviado_a_redaccion_informe', 'n3__informe_cargado', 'n4__certificado_descargado',
        'n5__fecha_de_formalizacion', 'n7__enviado_al_dto_juridico', 'n7__fecha_de_resolucion',
        'n9__enviado_a_legales', 'n13__fecha_recurso_alzada', 'nacionalidad_concedida',
        'formalizacion_r__alzada', 'tasa_pagada', 'enviado_a_pago_de_tasas',
        'fase_1_preestab', 'fase_1_pagado', 'fase_1_pagado__teamleader_', 'monto_fase_1_pagado',
        'fase_2_preestab', 'fase_2_pagado', 'fase_2_pagado__teamleader_', 'monto_fase_2_pagado',
        'fase_3_preestab', 'fase_3_pagado', 'fase_3_pagado__teamleader_', 'monto_fase_3_pagado',
        'carta_nat_preestab', 'carta_nat_pagado', 'cil___fcje_preestab', 'cil___fcje_pagado',
    ];

    // COS also derives these dates from associated services; the portal has no such properties.
    public const LOCAL_DEAL = [
        'titulo', 'fecha_solicitud_recurso_urgencia', 'fecha_solicitud_recursoalzada',
        'fecha_solicitud_resolucionexpresa', 'fecha_solicitud_viajudicial',
        'carta_nat_montopagado', 'cilfcje_montopagado',
    ];

    public static function teamleaderProject(): array
    {
        $fields = [
            'servicio' => DeterministicTeamleaderDealMatcher::SERVICE_FIELD,
            'codigo_de_proceso' => DeterministicTeamleaderDealMatcher::PROCESS_FIELD,
            'certificado' => DeterministicTeamleaderDealMatcher::CERTIFICATE_FIELD,
        ];
        foreach (TeamleaderProjectPaymentAnalyzer::PHASE_FIELDS as $phase => $definition) {
            $fields["fase_{$phase}_preestab"] = $definition['preestab']['id'];
            $fields["fase_{$phase}_pagado"] = $definition['paid']['id'];
        }
        return $fields;
    }

    public static function supportsHubspotProperty(string $type, string $property): bool
    {
        return match ($type) {
            'contact.propertyChange' => in_array($property, self::HUBSPOT_CONTACT, true),
            'deal.propertyChange' => in_array($property, self::HUBSPOT_DEAL, true),
            default => false,
        };
    }

    public static function projectState(array $project): array
    {
        $fields = [];
        $allowed = array_flip(self::teamleaderProject());
        foreach ($project['custom_fields'] ?? [] as $field) {
            $id = $field['definition']['id'] ?? $field['id'] ?? '';
            if (isset($allowed[$id])) $fields[$id] = $field['value'] ?? null;
        }
        ksort($fields);
        $customer = $project['customer'] ?? $project['lead']['customer'] ?? [];
        return [
            'title' => $project['title'] ?? null, 'status' => $project['status'] ?? null,
            'customer_id' => $customer['id'] ?? null, 'customer_type' => $customer['type'] ?? null,
            'fields' => $fields,
        ];
    }
}
