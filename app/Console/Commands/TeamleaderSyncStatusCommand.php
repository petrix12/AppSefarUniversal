<?php
// app/Console/Commands/TeamleaderSyncStatusCommand.php

namespace App\Console\Commands;

use App\Models\TlSyncLog;
use App\Models\TlContact;
use App\Models\TlCompany;
use App\Models\TlDeal;
use App\Models\TlProject;
use App\Models\TlInvoice;
use App\Models\TlDocument;
use Illuminate\Console\Command;

class TeamleaderSyncStatusCommand extends Command
{
    protected $signature   = 'teamleader:status';
    protected $description = 'Ver estado de la sincronización de Teamleader';

    public function handle(): void
    {
        $this->info("📊 Estado de Sincronización — Teamleader");
        $this->newLine();

        // Conteos en BD
        $this->table(
            ['Entidad', 'En BD', 'Documentos descargados', 'Documentos pendientes'],
            [
                ['Contactos',  TlContact::count(), '-', '-'],
                ['Empresas',   TlCompany::count(), '-', '-'],
                ['Deals',      TlDeal::count(),    '-', '-'],
                ['Proyectos',  TlProject::count(), '-', '-'],
                ['Facturas',   TlInvoice::count(), '-', '-'],
                ['Documentos', TlDocument::count(),
                    TlDocument::where('downloaded', true)->count(),
                    TlDocument::where('downloaded', false)->count(),
                ],
            ]
        );

        // Últimos logs
        $this->newLine();
        $this->info("📋 Últimos logs de sync:");

        $logs = TlSyncLog::latest()->take(10)->get();

        $this->table(
            ['ID', 'Entidad', 'Estado', 'Total', 'Procesados', 'Fallidos', 'Duración'],
            $logs->map(fn($log) => [
                $log->id,
                $log->entity,
                match($log->status) {
                    'completed' => '✅ Completado',
                    'running'   => '🔄 Corriendo',
                    'failed'    => '❌ Fallido',
                    default     => $log->status,
                },
                $log->total,
                $log->processed,
                $log->failed,
                $log->started_at && $log->finished_at
                    ? $log->started_at->diffForHumans($log->finished_at, true)
                    : ($log->started_at ? '⏳ ' . $log->started_at->diffForHumans() : '-'),
            ])
        );
    }
}
