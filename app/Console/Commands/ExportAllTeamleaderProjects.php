<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\TeamleaderService;

class ExportAllTeamleaderProjects extends Command
{
    protected $signature = 'teamleader:export-all-projects';
    protected $description = 'Exporta todos los proyectos de Teamleader (con campo PRODUCTO) a un único JSON en storage.';

    public function handle(TeamleaderService $service)
    {
        $this->info('⏳ Iniciando exportación de proyectos desde Teamleader...');

        $allProjects = [];
        $page = 1;
        $delayBetweenPages = 10;   // segundos entre páginas
        $retryDelay = 30;          // segundos de espera en caso de error

        $finalPage = false;

        do {
            $this->info("➡️ Procesando página {$page}...");

            while (true) {
                try {
                    $data = $service->listProjectsPage($page);
                    $projects = $data['data'] ?? [];

                    // ✅ acumular en un solo array
                    $allProjects = array_merge($allProjects, $projects);

                    $this->info("✅ Página {$page} completada con " . count($projects) . " proyectos.");

                    // Verificar si es la última página
                    if (count($projects) < 100) {
                        $finalPage = true;
                    } else {
                        $page++;
                        $this->info("⏸ Esperando {$delayBetweenPages} segundos antes de la siguiente página...");
                        sleep($delayBetweenPages);
                    }

                    // ✅ Salimos del bucle de reintentos porque funcionó
                    break;
                } catch (\Exception $e) {
                    $this->error("⚠️ Error en {$e->getFile()} línea {$e->getLine()}: " . $e->getMessage());
                    $this->warn("⏳ Reintentando página {$page} en {$retryDelay} segundos...");
                    sleep($retryDelay);
                }
            }

        } while (!$finalPage);

        // Guardar en un único JSON
        $path = storage_path('app/teamleader/all_projects.json');
        if (!file_exists(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        file_put_contents(
            $path,
            json_encode($allProjects, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        $this->info("🎉 Exportación completada.");
        $this->info("📊 Total proyectos exportados: " . count($allProjects));
        $this->info("📂 Archivo generado en: {$path}");

        return Command::SUCCESS;
    }
}
