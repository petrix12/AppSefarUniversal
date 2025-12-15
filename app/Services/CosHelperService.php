<?php

namespace App\Services;

use App\Models\Cos;
use Illuminate\Support\Facades\Cache;

class CosHelperService
{
    /**
     * Devuelve la estructura completa del COS desde BD.
     * Mantiene el orden por:
     * - fases.numero
     * - pasos.numero
     * - subfases.id (mejor si luego agregas un campo "orden")
     * - items.id (mejor si luego agregas un campo "orden")
     */
    public function get(bool $useCache = true): array
    {
        $builder = function (): array {
            $cosList = Cos::query()
                ->with([
                    'fases' => fn ($q) => $q->orderBy('orden')->orderBy('id'),
                    'fases.pasos' => fn ($q) => $q->orderBy('numero'),
                    'fases.pasos.subfases' => fn ($q) => $q->orderBy('id'),
                    'fases.pasos.items' => fn ($q) => $q->orderBy('id'),
                    'fases.pasos.textosAdicionales' => fn ($q) => $q->orderBy('id'),
                ])
                ->orderBy('id')
                ->get();

            // Formato compatible con tu viejo helper: [CosNombre => [FaseTitulo => [pasos...]]]
            $out = [];

            foreach ($cosList as $cos) {
                $fasesOut = [];

                foreach ($cos->fases as $fase) {
                    $pasosOut = [];

                    foreach ($fase->pasos as $paso) {
                        $pasosOut[] = [
                            'paso' => (int) $paso->numero,
                            'nombre_largo' => $paso->titulo,
                            'nombre_corto' => $paso->nombre_corto,
                            'promesa' => $paso->promesa,

                            // si no usas main_cta, puedes borrarlo
                            'main_cta' => [
                                'texto' => $paso->main_cta_texto,
                                'url'   => $paso->main_cta_url,
                            ],

                            // CTAs desde cos_items tipo = 'cta'
                            'ctas' => $paso->items
                                ->where('tipo', 'cta')
                                ->values()
                                ->map(fn ($i) => [
                                    'text' => $i->texto,
                                    'url'  => $i->url,
                                ])
                                ->all(),

                            // Subfases: lista de títulos
                            'subfases' => $paso->subfases
                                ->values()
                                ->map(fn ($s) => $s->titulo)
                                ->all(),

                            'textos_adicionales' => $paso->textosAdicionales
                                ->values()
                                ->map(fn ($t) => [
                                    'nombre' => $t->nombre,
                                    'texto'  => $t->texto,
                                ])
                                ->all(),
                        ];
                    }

                    $fasesOut[$fase->titulo] = $pasosOut;
                }

                $out[$cos->nombre] = $fasesOut;
            }

            return $out;
        };

        if (! $useCache) {
            return $builder();
        }

        return Cache::remember('cos.estructura', 3600, $builder);
    }

    public function clearCache(): void
    {
        Cache::forget('cos.estructura');
    }
}
