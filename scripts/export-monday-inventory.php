<?php

// Read-only inventory. Credentials are supplied through the process environment.
require __DIR__ . '/../vendor/autoload.php';

$token = getenv('MONDAY_INVENTORY_TOKEN');
if (! $token) {
    fwrite(STDERR, "Missing MONDAY_INVENTORY_TOKEN\n");
    exit(1);
}

$client = new \GuzzleHttp\Client(['timeout' => 45, 'connect_timeout' => 10]);
$boards = [];
$page = 1;
do {
    $response = $client->post('https://api.monday.com/v2', [
        'http_errors' => false,
        'headers' => ['Authorization' => $token, 'Content-Type' => 'application/json'],
        'json' => [
            'query' => 'query ($page: Int!) { boards(limit: 50, page: $page, state: all) { id name description state board_kind items_count workspace { id name } columns { id title type } groups { id title } } }',
            'variables' => ['page' => $page],
        ],
    ]);
    $data = json_decode((string) $response->getBody(), true);
    if ($response->getStatusCode() !== 200 || ! empty($data['errors']) || ! isset($data['data']['boards'])) {
        fwrite(STDERR, json_encode(['http_status' => $response->getStatusCode(), 'errors' => $data['errors'] ?? 'No boards returned'], JSON_UNESCAPED_UNICODE) . "\n");
        exit(1);
    }
    $batch = $data['data']['boards'];
    foreach ($batch as $board) {
        $boards[$board['id']] = $board;
    }
    fwrite(STDOUT, 'Page ' . $page . ': ' . count($batch) . ' boards; total ' . count($boards) . "\n");
    $page++;
} while (count($batch) === 50);

$source = file_get_contents(__DIR__ . '/../app/Services/ClientCosSnapshotService.php');
preg_match('/MONDAY_BOARD_IDS\s*=\s*\[(.*?)\];/s', $source, $match);
preg_match_all('/\b\d+\b/', $match[1] ?? '', $ids);
$cosIds = array_values(array_unique($ids[0]));
$missing = array_values(array_diff($cosIds, array_map('strval', array_keys($boards))));
foreach ($boards as &$board) {
    $board['used_in_cos_search'] = in_array((string) $board['id'], $cosIds, true);
    $board['has_cos_link_column'] = in_array('enlace', array_column($board['columns'], 'id'), true);
    $board['has_cos_labels_column'] = in_array('men__desplegable', array_column($board['columns'], 'id'), true);
}
unset($board);
uasort($boards, fn ($a, $b) => [$b['used_in_cos_search'], $a['name']] <=> [$a['used_in_cos_search'], $b['name']]);
$directory = __DIR__ . '/../storage/app/private/monday-inventory-' . date('Y-m-d-His');
mkdir($directory, 0700, true);
$inventory = [
    'retrieved_at' => gmdate(DATE_ATOM),
    'scope' => 'All boards accessible to this token, including archived/deleted when returned by the API. No item rows downloaded.',
    'board_count' => count($boards),
    'cos_search_ids' => $cosIds,
    'cos_ids_not_returned' => $missing,
    'boards' => array_values($boards),
];
file_put_contents($directory . '/inventory.json', json_encode($inventory, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

$escape = fn ($text) => str_replace(["\r", "\n", '|'], ['', ' ', '\\|'], (string) $text);
$markdown = "# Inventario de Monday\n\nConsulta: " . $inventory['retrieved_at'] . ".\n\n" . count($boards) . " tableros accesibles. Incluye columnas y grupos; no incluye filas de clientes.\n\n";
$markdown .= "Las sugerencias se basan en la configuración del COS y la estructura de cada tablero; no autorizan borrar tableros ni certifican que sus clientes estén presentes en otro lugar.\n\n";
$markdown .= "| Tablero | ID | Estado | Espacio | Filas (conteo) | Columnas | Grupos | Búsqueda COS | Columna enlace |\n|---|---|---|---|---:|---:|---:|---|---|\n";
foreach ($boards as $board) {
    $markdown .= '| ' . implode(' | ', array_map($escape, [
        $board['name'], $board['id'], $board['state'], $board['workspace']['name'] ?? '',
        $board['items_count'], count($board['columns']), count($board['groups']),
        $board['used_in_cos_search'] ? 'Sí' : 'No', $board['has_cos_link_column'] ? 'Sí' : 'No',
    ])) . " |\n";
}
if ($missing) {
    $markdown .= "\nIDs del COS no devueltos por la API (puede deberse a permisos o estado): " . implode(', ', $missing) . ".\n";
}
foreach ($boards as $board) {
    $markdown .= "\n## " . $escape($board['name']) . ' — ' . $board['id'] . "\n\n";
    if ($board['used_in_cos_search'] && ! $board['has_cos_link_column']) {
        $markdown .= "Revisar/excluir de la búsqueda actual: falta la columna con ID `enlace` que utiliza el COS. Puede requerir adaptar el mapeo.\n\n";
    } elseif ($board['used_in_cos_search']) {
        $markdown .= "Conservar provisionalmente: está en la búsqueda COS y dispone de la columna `enlace`. Revisar estado y uso operativo antes de excluir.\n\n";
    } else {
        $markdown .= "Fuera de la lista actual de búsqueda COS; no añade llamadas a ese recorrido.\n\n";
    }
    $markdown .= $escape($board['description'] ?? '') . "\n\n### Columnas\n\n| ID | Nombre | Tipo |\n|---|---|---|\n";
    foreach ($board['columns'] as $column) {
        $markdown .= '| ' . implode(' | ', array_map($escape, [$column['id'], $column['title'], $column['type']])) . " |\n";
    }
    $markdown .= "\n### Grupos\n\n| ID | Nombre |\n|---|---|\n";
    foreach ($board['groups'] as $group) {
        $markdown .= '| ' . implode(' | ', array_map($escape, [$group['id'], $group['title']])) . " |\n";
    }
}
file_put_contents($directory . '/inventory.md', $markdown);
fwrite(STDOUT, json_encode(['directory' => realpath($directory), 'boards' => count($boards), 'cos_not_returned' => $missing], JSON_UNESCAPED_SLASHES) . "\n");
