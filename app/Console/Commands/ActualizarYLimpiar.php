<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class ActualizarYLimpiar extends Command
{
    protected $signature = 'app:actualizar-y-limpiar';
    protected $description = 'Actualiza el código desde Git y limpia la caché solo si hay cambios.';

    public function handle()
    {
        // Cambiar al directorio raíz del proyecto
        $projectRoot = '/home/u530524868/public_html/public_html';
        chdir($projectRoot);

        // Ejecutar git pull y capturar la salida
        $output = [];
        exec('git pull', $output);

        // Unir la salida en un solo string para verificar el mensaje
        $outputString = implode("\n", $output);

        // Verificar si el mensaje contiene "Already up to date"
        if (strpos($outputString, 'Already up to date') !== false) {
            // No hay cambios, no se limpia la caché
            $this->info('El código ya está actualizado. No es necesario limpiar la caché.');
        } else {
            // Hay cambios, proceder con la limpieza de caché
            Artisan::call('optimize:clear', ['--output' => 'log.txt']);
            $this->info('Código actualizado y caché limpiada.');
        }
    }
}
