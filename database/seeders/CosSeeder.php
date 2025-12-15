<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Cos;
use App\Models\CosFase;
use App\Models\CosPaso;
use App\Models\CosSubfase;
use App\Models\CosItem;
use App\Models\CosTextoAdicional;

class CosSeeder extends Seeder
{
    public function run()
    {
        $cosData = include(database_path('seeders/data/cos.php'));

        foreach ($cosData as $cosNombre => $fasesData) {

            // Crear COS
            $cos = Cos::create([
                'nombre' => $cosNombre,
            ]);

            $numeroFase = 1;

            foreach ($fasesData as $faseNombre => $pasosArray) {

                // Crear Fase (sin ID del helper)
                $fase = CosFase::create([
                    'cos_id' => $cos->id,
                    'numero' => $numeroFase++,
                    'titulo' => $faseNombre,
                ]);

                foreach ($pasosArray as $pasoData) {

                    // Crear Paso (sin ID del helper, usando "paso" => 1,2,3...)
                    $paso = CosPaso::create([
                        'fase_id' => $fase->id,
                        'numero' => $pasoData['paso'],
                        'titulo' => $pasoData['nombre_largo'],
                        'nombre_corto' => $pasoData['nombre_corto'] ?? null,
                        'promesa' => $pasoData['promesa'] ?? null,
                        'main_cta_texto' => $pasoData['main_cta']['texto'] ?? null,
                        'main_cta_url' => $pasoData['main_cta']['url'] ?? null,
                    ]);

                    // =====================================================
                    // TEXTOS ADICIONALES
                    // =====================================================
                    if (!empty($pasoData['textos_adicionales'])) {
                        foreach ($pasoData['textos_adicionales'] as $txt) {
                            CosTextoAdicional::create([
                                'paso_id' => $paso->id,
                                'nombre' => $txt['nombre'] ?? null,
                                'texto' => $txt['texto'] ?? null,
                            ]);
                        }
                    }

                    // =====================================================
                    // CTAs DEL PASO (tipo = cta)
                    // =====================================================
                    if (!empty($pasoData['ctas'])) {
                        foreach ($pasoData['ctas'] as $cta) {
                            CosItem::create([
                                'paso_id' => $paso->id,
                                'subfase_id' => null,
                                'tipo' => 'cta',
                                'texto' => $cta['text'] ?? $cta['texto'] ?? null,
                                'url'   => $cta['url'] ?? null,
                            ]);
                        }
                    }

                    // =====================================================
                    // SUBFASES
                    // =====================================================
                    if (!empty($pasoData['subfases'])) {

                        foreach ($pasoData['subfases'] as $subfaseData) {

                            $subfase = CosSubfase::create([
                                'paso_id' => $paso->id,
                                'titulo' => $subfaseData['titulo'] ?? null,
                            ]);

                            // SUBITEMS (tipo = subitem)
                            if (!empty($subfaseData['items'])) {
                                foreach ($subfaseData['items'] as $item) {
                                    CosItem::create([
                                        'paso_id' => null,
                                        'subfase_id' => $subfase->id,
                                        'tipo' => 'subitem',
                                        'texto' => $item['texto'] ?? $item['text'] ?? null,
                                        'url'   => $item['url'] ?? null,
                                    ]);
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
