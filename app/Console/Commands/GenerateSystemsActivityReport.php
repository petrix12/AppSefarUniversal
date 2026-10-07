<?php

namespace App\Console\Commands;

use App\Services\SystemsActivityReportService;
use Illuminate\Console\Command;

class GenerateSystemsActivityReport extends Command
{
    protected $signature = 'systems:activity-report {--date= : Fecha del informe en formato YYYY-MM-DD}';

    protected $description = 'Genera resúmenes diarios de actividad para los usuarios de Sistemas y los guarda en Trello.';

    public function handle(SystemsActivityReportService $reports): int
    {
        try {
            $results = $reports->generate($this->option('date'));
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }

        foreach ($results as $result) {
            $this->line(($result['display_name'] ?? 'Usuario').': '.$result['status']);
        }

        return collect($results)->contains(fn (array $result): bool => $result['status'] === 'failed')
            ? self::FAILURE
            : self::SUCCESS;
    }
}
