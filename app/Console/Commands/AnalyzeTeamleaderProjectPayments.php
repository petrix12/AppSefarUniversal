<?php

namespace App\Console\Commands;

use App\Models\TlProject;
use App\Services\TeamleaderProjectPaymentAnalyzer;
use Illuminate\Console\Command;

class AnalyzeTeamleaderProjectPayments extends Command
{
    protected $signature = 'teamleader:analyze-project-payments
        {--limit=50 : Maximo de proyectos a analizar}
        {--project= : ID de proyecto Teamleader especifico}
        {--customer= : ID de contacto/cliente Teamleader}
        {--only-with-balance : Mostrar solo fases con saldo pendiente}
        {--json : Imprimir resultado completo en JSON}';

    protected $description = 'Analiza montos preestablecidos, pagados/abonados y saldos desde custom fields de proyectos Teamleader.';

    public function handle(TeamleaderProjectPaymentAnalyzer $analyzer): int
    {
        $query = TlProject::query()
            ->select(['id', 'title', 'customer_id', 'customer_type', 'custom_fields', 'tl_updated_at', 'updated_at'])
            ->whereNotNull('custom_fields')
            ->orderByDesc('tl_updated_at')
            ->orderByDesc('updated_at');

        if ($projectId = $this->option('project')) {
            $query->where('id', $projectId);
        }

        if ($customerId = $this->option('customer')) {
            $query->where('customer_id', $customerId);
        }

        $limit = max(1, (int) $this->option('limit'));
        $jsonProjects = [];
        $rows = [];
        $totals = [
            'projects' => 0,
            'preestab_amount' => 0.0,
            'paid_amount' => 0.0,
            'balance_amount' => 0.0,
            'overpaid_amount' => 0.0,
        ];

        $query->chunk(100, function ($projects) use ($analyzer, $limit, &$jsonProjects, &$rows, &$totals) {
            foreach ($projects as $projectModel) {
                if ($totals['projects'] >= $limit) {
                    return false;
                }

                $project = $analyzer->analyzeProject($projectModel);
                $totals['projects']++;
                $totals['preestab_amount'] += $project['totals']['preestab_amount'];
                $totals['paid_amount'] += $project['totals']['paid_amount'];
                $totals['balance_amount'] += $project['totals']['balance_amount'];
                $totals['overpaid_amount'] += $project['totals']['overpaid_amount'];

                if ($this->option('json')) {
                    $jsonProjects[] = $project;
                }

                foreach ($project['phases'] as $phase) {
                    if ($this->option('only-with-balance') && $phase['balance_amount'] <= 0) {
                        continue;
                    }

                    $rows[] = [
                        'Proyecto' => $project['project_title'] ?: $project['project_id'],
                        'Fase' => $phase['phase'],
                        'Preestab' => $this->money($phase['effective_preestab_amount']),
                        'Pagado/abonado' => $this->money($phase['effective_paid_amount']),
                        'Saldo' => $this->money($phase['balance_amount']),
                        'Estado' => $phase['status'],
                        'Raw preestab' => $this->short($phase['preestab_raw']),
                        'Raw pagado' => $this->short($phase['paid_raw']),
                    ];
                }
            }

            return $totals['projects'] < $limit;
        });

        $analysis = [
            'projects' => $jsonProjects,
            'totals' => [
                'projects' => $totals['projects'],
                'preestab_amount' => round($totals['preestab_amount'], 2),
                'paid_amount' => round($totals['paid_amount'], 2),
                'balance_amount' => round($totals['balance_amount'], 2),
                'overpaid_amount' => round($totals['overpaid_amount'], 2),
            ],
        ];

        if ($this->option('json')) {
            $this->line(json_encode($analysis, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->table(
            ['Proyecto', 'Fase', 'Preestab', 'Pagado/abonado', 'Saldo', 'Estado', 'Raw preestab', 'Raw pagado'],
            $rows
        );

        $this->newLine();
        $this->info('Totales analizados');
        $this->line('Proyectos: ' . $analysis['totals']['projects']);
        $this->line('Preestablecido: ' . $this->money($analysis['totals']['preestab_amount']));
        $this->line('Pagado/abonado: ' . $this->money($analysis['totals']['paid_amount']));
        $this->line('Saldo pendiente: ' . $this->money($analysis['totals']['balance_amount']));
        $this->line('Sobrepago: ' . $this->money($analysis['totals']['overpaid_amount']));

        return self::SUCCESS;
    }

    private function money(float|int $amount): string
    {
        return number_format((float) $amount, 2, ',', '.') . ' EUR';
    }

    private function short(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '-';
        }

        return mb_strlen($value) > 42 ? mb_substr($value, 0, 39) . '...' : $value;
    }
}
