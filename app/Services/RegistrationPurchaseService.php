<?php

namespace App\Services;

use App\Models\Servicio;
use App\Models\User;
use Illuminate\Support\Str;

class RegistrationPurchaseService
{
    public function data(User $user, Servicio $service, array $metadata = []): array
    {
        if (in_array((string) $user->tiene_hermanos, ['1', 'Si'], true) && ! Str::endsWith($service->id_hubspot, ' - Hermano')) {
            $service = Servicio::where('id_hubspot', $service->id_hubspot.' - Hermano')->first() ?? $service;
        }
        $price = $service->priceSnapshot();
        $quantity = $service->id_hubspot === 'Recurso de Alzada' ? max(1, (int) $user->cantidad_alzada) : 1;
        $code = $service->id_hubspot;
        $normalized = Str::lower(Str::ascii($code));
        if (in_array($code, ['Española LMD', 'Italiana'], true)) {
            $description = 'Pago Fase Inicial: Investigación Preliminar y Preparatoria: '.$service->nombre;
            if ($code === 'Italiana' && (int) $user->antepasados === 1) $description .= ' + (Consulta Gratuita)';
        } elseif ($code === 'Gestión Documental' || (str_contains($normalized, 'auditoria') && str_contains($normalized, 'procedimiento'))) {
            $description = $service->nombre;
        } elseif ((int) $service->tipov === 1) {
            $description = 'Servicios para Vinculaciones: '.$service->nombre;
        } else {
            $description = 'Análisis genealógico: '.$service->nombre;
        }

        return [
            'id_user' => $user->id,
            'servicio_id' => $service->id,
            'servicio_hs_id' => $code,
            'descripcion' => $description,
            'pagado' => 0,
            'monto' => $price['unit_price'] * $quantity,
            'metadata' => array_merge($metadata, ['pricing' => $price + ['quantity' => $quantity]]),
        ];
    }
}
