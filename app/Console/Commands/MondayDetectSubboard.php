<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class MondayDetectSubboard extends Command
{
    protected $signature = 'monday:detect-subboard {--board=765394861}';
    protected $description = 'Detecta el ID del subboard (subtasks/subitems board) desde monday leyendo settings_str';

    public function handle()
    {
        $boardId = (int)$this->option('board');
        $token = env('MONDAY_TOKEN') ?? config('services.monday.token');

        if (!$token) {
            $this->error('Falta MONDAY_TOKEN');
            return self::FAILURE;
        }

        // Traemos columnas con settings_str (aquí suele venir el board interno de subtasks)
        $query = '
        query ($boardId: ID!) {
          boards(ids: [$boardId]) {
            id
            name
            columns {
              id
              title
              type
              settings_str
            }
          }
        }';

        $res = Http::withHeaders([
            'Authorization' => $token,
            'Content-Type' => 'application/json',
        ])->post('https://api.monday.com/v2', [
            'query' => $query,
            'variables' => ['boardId' => $boardId],
        ]);

        if (!$res->successful()) {
            $this->error("HTTP {$res->status()}: {$res->body()}");
            return self::FAILURE;
        }

        $json = $res->json();
        if (!empty($json['errors'])) {
            $this->error('GraphQL errors: ' . json_encode($json['errors']));
            return self::FAILURE;
        }

        $boardName = data_get($json, 'data.boards.0.name', '—');
        $columns = data_get($json, 'data.boards.0.columns', []);

        // Columna subtasks: en tu caso es "subelementos"
        $subCol = collect($columns)->firstWhere('type', 'subtasks')
               ?? collect($columns)->firstWhere('id', 'subelementos');

        if (!$subCol) {
            $this->warn('No encontré ninguna columna type=subtasks');
            return self::SUCCESS;
        }

        $settingsStr = $subCol['settings_str'] ?? '';
        $settings = null;

        if ($settingsStr) {
            $settings = json_decode($settingsStr, true);
        }

        // monday suele guardar aquí el boardId/boardIds del subitems board
        $possible = [
            data_get($settings, 'boardId'),
            data_get($settings, 'board_id'),
            data_get($settings, 'subitems_board_id'),
            data_get($settings, 'subitemsBoardId'),
        ];

        // A veces viene como array
        $boardIds = data_get($settings, 'boardIds') ?? data_get($settings, 'board_ids');
        if (is_array($boardIds) && count($boardIds)) {
            $possible[] = $boardIds[0];
        }

        $subBoardId = collect($possible)->filter(fn($v) => !empty($v))->first();

        $this->info("Board: {$boardId} ({$boardName})");
        $this->info("Columna subtasks: {$subCol['id']} ({$subCol['title']})");

        if ($subBoardId) {
            $this->info("✅ SUBBOARD ID detectado: {$subBoardId}");
        } else {
            $this->warn("No pude inferir el subboard id desde settings_str.");
            $this->line("settings_str: " . $settingsStr);
            $this->line("Siguiente opción: buscar un item que sí tenga subitems (te lo armo si quieres).");
        }

        return self::SUCCESS;
    }
}
