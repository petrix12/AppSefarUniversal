<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class MondayExportBoard extends Command
{
    protected $signature = 'monday:export-board
        {--board= : ID del board}
        {--group= : ID del grupo a exportar (ej: grupo_nuevo34135)}
        {--format=json : json o csv}
        {--path=exports/monday : carpeta dentro de storage/app}
        {--limit=500 : items por página (max 500)}
        {--include-ids=0 : en CSV, incluir también columnas por id (1/0)}';

    protected $description = 'Exporta SOLO items de un board (opcionalmente por group_id). Genera JSON con titles o CSV';

    public function handle(): int
    {
        $boardId = (int)($this->option('board') ?? 0);
        $groupId = (string)($this->option('group') ?? '');
        $format = strtolower((string)$this->option('format'));
        $path = trim((string)$this->option('path'), '/');
        $limit = (int)($this->option('limit') ?? 500);
        $includeIds = (int)($this->option('include-ids') ?? 0) === 1;

        if ($boardId <= 0) {
            $this->error('Debes indicar --board=ID');
            return self::FAILURE;
        }
        if ($limit <= 0 || $limit > 500) {
            $this->error('El --limit debe ser entre 1 y 500');
            return self::FAILURE;
        }
        if (!in_array($format, ['json', 'csv'], true)) {
            $this->error('Formato no soportado. Usa --format=json o --format=csv');
            return self::FAILURE;
        }

        $token = env('MONDAY_TOKEN') ?? config('services.monday.token');
        if (!$token) {
            $this->error('Falta MONDAY_TOKEN. Agrega en .env o config/services.php');
            return self::FAILURE;
        }

        // 1) Meta del board + columns (para titles)
        $meta = $this->mondayQuery($token, '
            query ($boardId: ID!) {
              boards(ids: [$boardId]) {
                id
                name
                columns { id title type }
              }
            }
        ', ['boardId' => $boardId]);

        $boardName = data_get($meta, 'data.boards.0.name', 'board');
        $columns = data_get($meta, 'data.boards.0.columns', []);

        // Mapa: column_id => ['title'=>..., 'type'=>...]
        $colMap = [];
        foreach ($columns as $c) {
            $colMap[(string)$c['id']] = [
                'title' => (string)($c['title'] ?? $c['id']),
                'type' => (string)($c['type'] ?? ''),
            ];
        }

        // 2) Paginación items
        $items = [];
        $cursor = null;

        $firstPage = $this->mondayQuery($token, '
            query ($boardId: ID!, $limit: Int!) {
              boards(ids: [$boardId]) {
                items_page(limit: $limit) {
                  cursor
                  items {
                    id
                    name
                    group { id title }
                    column_values { id type text value }
                  }
                }
              }
            }
        ', ['boardId' => $boardId, 'limit' => $limit]);

        $cursor = data_get($firstPage, 'data.boards.0.items_page.cursor');
        $pageItems = data_get($firstPage, 'data.boards.0.items_page.items', []);
        $items = array_merge($items, $pageItems);

        while (!empty($cursor)) {
            $next = $this->mondayQuery($token, '
                query ($cursor: String!, $limit: Int!) {
                  next_items_page(limit: $limit, cursor: $cursor) {
                    cursor
                    items {
                      id
                      name
                      group { id title }
                      column_values { id type text value }
                    }
                  }
                }
            ', ['cursor' => $cursor, 'limit' => $limit]);

            $cursor = data_get($next, 'data.next_items_page.cursor');
            $pageItems = data_get($next, 'data.next_items_page.items', []);
            if (empty($pageItems)) break;

            $items = array_merge($items, $pageItems);
        }

        // 3) Filtrar por group_id si viene
        if (!empty($groupId)) {
            $items = array_values(array_filter($items, fn($it) => (string)data_get($it, 'group.id') === $groupId));
        }

        // 4) Preparar export (JSON enriquecido + CSV)
        $safeBoard = preg_replace('/[^a-zA-Z0-9\-_]+/', '_', $boardName);
        $safeGroup = $groupId ? preg_replace('/[^a-zA-Z0-9\-_]+/', '_', $groupId) : 'ALL';

        if ($format === 'json') {
            $exportItems = array_map(function ($it) use ($colMap) {
                // convierte column_values en array enriquecido + dict por title
                $enriched = [];
                $dictByTitle = [];

                foreach (($it['column_values'] ?? []) as $cv) {
                    $cid = (string)($cv['id'] ?? '');
                    $title = $colMap[$cid]['title'] ?? $cid;
                    $type = $cv['type'] ?? ($colMap[$cid]['type'] ?? null);

                    $row = [
                        'id' => $cid,
                        'title' => $title,
                        'type' => $type,
                        'text' => $cv['text'] ?? null,
                        'value' => $cv['value'] ?? null,
                    ];
                    $enriched[] = $row;

                    // dict por title (ideal para consumo)
                    $dictByTitle[$title] = $cv['text'] ?? null;
                }

                return [
                    'id' => $it['id'] ?? null,
                    'name' => $it['name'] ?? null,
                    'group' => [
                        'id' => data_get($it, 'group.id'),
                        'title' => data_get($it, 'group.title'),
                    ],
                    'column_values' => $enriched,
                    'columns' => $dictByTitle,
                ];
            }, $items);

            $payload = [
                'exported_at' => now()->toIso8601String(),
                'board' => ['id' => $boardId, 'name' => $boardName],
                'filters' => ['group_id' => $groupId ?: null],
                'columns_map' => $colMap,
                'stats' => [
                    'items_exported' => count($exportItems),
                ],
                'items' => $exportItems,
            ];

            $filename = "{$path}/board_{$boardId}_group_{$safeGroup}_{$safeBoard}.json";
            Storage::put($filename, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            $this->info("✅ JSON listo: storage/app/{$filename}");
            return self::SUCCESS;
        }

        // CSV
        // Encabezados: base + todas las columnas por title (y opcional por id)
        $baseHeaders = ['item_id', 'item_name', 'group_id', 'group_title'];

        // Mantener orden estable: según columns del board
        $titlesInOrder = array_map(fn($c) => (string)($c['title'] ?? $c['id']), $columns);
        $idsInOrder = array_map(fn($c) => (string)($c['id'] ?? ''), $columns);

        $headers = $baseHeaders;

        foreach ($titlesInOrder as $t) {
            $headers[] = $t;
        }

        if ($includeIds) {
            foreach ($idsInOrder as $cid) {
                if ($cid !== 'name') { // name lo tienes en item_name
                    $headers[] = "__id__{$cid}";
                }
            }
        }

        $lines = [];
        $lines[] = $this->csvLine($headers);

        foreach ($items as $it) {
            // armar mapa id=>text para lookup rápido
            $idToText = [];
            foreach (($it['column_values'] ?? []) as $cv) {
                $idToText[(string)($cv['id'] ?? '')] = $cv['text'] ?? '';
            }

            $row = [
                (string)($it['id'] ?? ''),
                (string)($it['name'] ?? ''),
                (string) data_get($it, 'group.id', ''),
                (string) data_get($it, 'group.title', ''),
            ];

            // por title: usamos idToText pero en orden de columns
            foreach ($columns as $c) {
                $cid = (string)($c['id'] ?? '');
                // el "name" no viene en column_values, pero igual lo dejamos vacío o repetimos item_name
                if ($cid === 'name') {
                    $row[] = (string)($it['name'] ?? '');
                    continue;
                }
                $row[] = (string)($idToText[$cid] ?? '');
            }

            if ($includeIds) {
                foreach ($columns as $c) {
                    $cid = (string)($c['id'] ?? '');
                    if ($cid === 'name') continue;
                    $row[] = (string)($idToText[$cid] ?? '');
                }
            }

            $lines[] = $this->csvLine($row);
        }

        $csv = implode("\n", $lines) . "\n";
        $filename = "{$path}/board_{$boardId}_group_{$safeGroup}_{$safeBoard}.csv";
        Storage::put($filename, $csv);

        $this->info("✅ CSV listo: storage/app/{$filename}");
        return self::SUCCESS;
    }

    private function mondayQuery(string $token, string $query, array $variables): array
    {
        $res = Http::withHeaders([
            'Authorization' => $token,
            'Content-Type' => 'application/json',
        ])->post('https://api.monday.com/v2', [
            'query' => $query,
            'variables' => $variables,
        ]);

        if (!$res->successful()) {
            throw new \RuntimeException('Error monday API: HTTP ' . $res->status() . ' - ' . $res->body());
        }

        $json = $res->json();

        if (!empty($json['errors'])) {
            throw new \RuntimeException('Error monday GraphQL: ' . json_encode($json['errors']));
        }

        return $json;
    }

    private function csvLine(array $fields): string
    {
        // CSV seguro: comillas dobles y escapar comillas internas
        $escaped = array_map(function ($v) {
            $s = (string)($v ?? '');
            $s = str_replace('"', '""', $s);
            return '"' . $s . '"';
        }, $fields);

        return implode(',', $escaped);
    }
}
