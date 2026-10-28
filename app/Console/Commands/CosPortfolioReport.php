<?php

namespace App\Console\Commands;

use App\Services\LocalCosPortfolioService;
use Illuminate\Console\Command;

class CosPortfolioReport extends Command
{
    protected $signature = 'cos:portfolio
        {--owner= : Limitar a un owner interno}
        {--output= : Guardar el informe completo en JSON}
        {--debtors-csv= : Guardar todos los clientes con saldo conocido en CSV}
        {--certified-only : Limitar el CSV a clientes con certificado registrado}
        {--apply-associations : Guardar únicamente las asociaciones inequívocas en la base local}';

    protected $description = 'Lista toda la cartera, certificados, saldos y asociaciones por reglas, sin IA ni peticiones externas.';

    public function handle(LocalCosPortfolioService $portfolio): int
    {
        $report = $portfolio->generate($this->option('owner') !== null ? (int) $this->option('owner') : null);
        $this->table(['Indicador', 'Cantidad'], collect($report['summary'])->map(fn ($value, $key) => [$key, $value])->values()->all());
        if ($this->option('apply-associations')) {
            $this->info('Asociaciones locales guardadas: '.$portfolio->applyMatches($report['associations']));
        }
        if ($path = $this->option('output')) {
            $written = file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            if ($written === false) {
                $this->error('No se pudo guardar el informe.');
                return self::FAILURE;
            }
            $this->info('Informe completo: '.$path);
        }
        if ($path = $this->option('debtors-csv')) {
            $stream = fopen($path, 'w');
            if ($stream === false) {
                $this->error('No se pudo guardar el CSV.');
                return self::FAILURE;
            }
            $rows = array_filter($report['rows'], fn ($row) => $row['known_balance_eur'] > 0.01
                && (! $this->option('certified-only') || $row['has_certificate']));
            $portfolio->writeCsv($stream, $rows);
            fclose($stream);
            $this->info('Lista completa de clientes con saldo: '.$path.' ('.count($rows).').');
        }
        $this->line('Los saldos son importados. Los importes pendientes de revisión no se suman al saldo conocido.');

        return self::SUCCESS;
    }
}
