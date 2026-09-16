<?php

namespace App\Services;

use App\Models\File;
use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use Illuminate\Support\Facades\Storage;

class CosDocumentImporter
{
    public function import(array $urls, $user, HubspotService $hubspotService): array
    {
        // 1. Configuración inicial
        $client = new Client(['timeout' => 15]);
        $processedFiles = [];

        // 2. Obtener archivos existentes en una sola consulta
        $existingFiles = File::where('IDCliente', $user->passport)
            ->pluck('file')
            ->toArray();

        // 3. Preparar promesas para descargas concurrentes
        $promises = [];
        $validFiles = [];

        foreach ($urls as $url) {
            try {
                $fileUrl = $hubspotService->getFileUrlFromFormIntegrations($url);
                if (!$fileUrl) continue;

                $filename = basename(parse_url($fileUrl, PHP_URL_PATH));
                $s3Path = "public/doc/{$user->passport}/{$filename}";

                // Verificar si ya existe
                if (in_array($filename, $existingFiles)) {
                    continue;
                }
                if (Storage::disk('s3')->exists($s3Path)) {
                    continue;
                }

                $validFiles[$filename] = $s3Path;
                $promises[$filename] = $client->getAsync($fileUrl);

            } catch (\Exception $e) {
                \Log::error("Error preparando descarga: {$e->getMessage()}");
            }
        }

        // 4. Ejecutar descargas concurrentes
        $responses = Promise\Utils::settle($promises)->wait();

        // 5. Procesar resultados
        foreach ($responses as $filename => $response) {
            if ($response['state'] !== 'fulfilled') {
                \Log::warning("Fallo descarga: {$filename}");
                continue;
            }

            try {
                $fileContent = $response['value']->getBody();
                $s3Path = $validFiles[$filename];

                // Subir a S3
                Storage::disk('s3')->put($s3Path, $fileContent);

                // Registrar en DB
                File::create([
                    'file' => $filename,
                    'location' => "public/doc/{$user->passport}/",
                    'IDCliente' => $user->passport,
                ]);

                $processedFiles[] = $filename;

            } catch (\Exception $e) {
                \Log::error("Error procesando {$filename}: {$e->getMessage()}");
            }
        }

        return $processedFiles;
    }
}
