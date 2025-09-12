<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\TeamleaderService;

class ExportTeamleaderProjects extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'teamleader:export-projects {--page=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Exporta todos los proyectos de Teamleader con el campo custom PRODUCTO a un JSON en storage.';

    /**
     * Execute the console command.
     */
    public function handle(TeamleaderService $service)
    {
        try {
            $page = (int) $this->option('page') ?: 1;
            $delay = 10; // segundos

            $this->info("⏳ Exportando página $page de proyectos desde Teamleader...");

            $data = $service->listProjectsPage($page);

            $projects = $data['data'] ?? [];
            $meta = $data['meta']['page'] ?? [];

            // Guardar en un JSON separado por página
            $path = storage_path("app/teamleader/projects_page_{$page}.json");
            if (!file_exists(dirname($path))) {
                mkdir(dirname($path), 0777, true);
            }

            file_put_contents($path, json_encode($projects, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            $this->info("✅ Página $page exportada con " . count($projects) . " proyectos.");
            $this->info("📂 Archivo generado en: $path");

            // Si quedan más páginas, procesarlas con delay
            $total = $meta['total'] ?? 0;
            $perPage = $meta['size'] ?? 100;
            $totalPages = ceil($total / $perPage);

            if ($page < $totalPages) {
                $this->info("⏭️ Aún hay más páginas ($totalPages en total). Esperando $delay segundos antes de continuar...");
                sleep($delay);

                // Recursivo: avanzar a la siguiente página
                $nextPage = $page + 1;
                $this->call('teamleader:export-projects', ['--page' => $nextPage]);
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

}
