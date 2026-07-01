<?php

namespace App\Console\Commands;

use App\Services\BancaOnlineCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class SyncBancaOnlineCatalog extends Command
{
    protected $signature = 'banca-online:sync-catalog
        {--country=espana : Pais a mostrar en el resumen}
        {--plan=solicitud-estrategica : Plan a mostrar en el resumen}';

    protected $description = 'Sincroniza el catalogo base de Banca Online sin borrar registros existentes.';

    public function handle(BancaOnlineCatalog $catalog): int
    {
        $missingColumns = $this->missingRequiredColumns();

        if (! empty($missingColumns)) {
            $this->error('Faltan columnas requeridas para Banca Online: ' . implode(', ', $missingColumns));
            $this->line('Ejecuta primero: php artisan migrate --force');

            return self::FAILURE;
        }

        $result = $catalog->syncBaseCatalog();

        $this->info("Catalogo sincronizado. Creados: {$result['created']}. Actualizados: {$result['updated']}.");

        $countrySlug = $catalog->normalizeCountry((string) $this->option('country'));
        $planSlug = (string) $this->option('plan');
        $plan = $catalog->planForCountry($countrySlug, $planSlug);

        if (! $plan) {
            $this->warn("No existe el plan '{$planSlug}' para el pais '{$countrySlug}'.");

            return self::SUCCESS;
        }

        $packages = $catalog->packagesForPlan($countrySlug, $planSlug, false);

        if ($packages->isEmpty()) {
            $this->warn("No hay modalidades para {$countrySlug}/{$planSlug}.");

            return self::SUCCESS;
        }

        $this->newLine();
        $this->line(($plan['public_title'] ?? $plan['title'] ?? $planSlug) . " ({$countrySlug})");

        $this->table(
            ['Nivel', 'Modalidad', 'Precio lista', 'Ahorro', 'Total', 'Beneficios', 'Activo'],
            $packages->map(fn ($package) => [
                $catalog->metadata($package)['tier_slug'] ?? '-',
                $package->nombre,
                number_format($catalog->packageSubtotal($package), 0, ',', '.') . ' EUR',
                number_format($catalog->packageDiscount($package), 0, ',', '.') . ' EUR',
                number_format($catalog->packageTotal($package), 0, ',', '.') . ' EUR',
                $catalog->packageDisplayItems($package, false)->count(),
                $package->activo ? 'si' : 'no',
            ])->all()
        );

        return self::SUCCESS;
    }

    private function missingRequiredColumns(): array
    {
        $required = [
            'servicios' => ['categoria', 'tipo', 'descripcion_publica', 'activo', 'visible_cliente', 'moneda', 'orden', 'metadata'],
            'compras' => ['servicio_id', 'source', 'metadata', 'paid_at'],
        ];

        $missing = [];

        foreach ($required as $table => $columns) {
            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    $missing[] = "{$table}.{$column}";
                }
            }
        }

        return $missing;
    }
}
