<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class CosMondayLookup
{
    public function find(?string $passport, User $user): ?array
    {
        $passport = trim((string) $passport);
        $ids = array_keys(config('cos_snapshot.monday_search_boards', []));
        if ($passport === '' || $ids === []) {
            return null;
        }

        $key = 'cos.monday.lookup.' . hash('sha256', json_encode([$user->id, $passport, $ids]));
        // Wrap null so a successful search with no matches is cached too.
        $result = Cache::remember($key, 60, function () use ($passport, $ids) {
            $token = config('services.monday.token');
            if (blank($token)) {
                throw new RuntimeException('Falta configurar MONDAY_TOKEN.');
            }

            $url = json_encode('https://app.sefaruniversal.com/tree/' . $passport, JSON_THROW_ON_ERROR);
            $fields = [];
            foreach ($ids as $index => $id) {
                if (! ctype_digit((string) $id)) {
                    throw new RuntimeException('ID de tablero COS inválido.');
                }
                $fields[] = "b{$index}: items_page_by_column_values(limit: 1, board_id: {$id}, columns: [{column_id: \"enlace\", column_values: [{$url}]}]) { items { id name board { id name } column_values { id text value column { title type } } } }";
            }

            $started = microtime(true);
            $data = ['data' => []];
            // Bounded concurrency: never start more than 12 requests at once.
            foreach (array_chunk($fields, 12) as $chunk) {
                $responses = Http::pool(function (\Illuminate\Http\Client\Pool $pool) use ($chunk, $token) {
                    return array_map(fn ($field) => $pool->withToken($token)->connectTimeout(5)->timeout(20)
                        ->post('https://api.monday.com/v2', ['query' => 'query { ' . $field . ' }']), $chunk);
                });
                foreach ($responses as $response) {
                    if ($response instanceof \Throwable) {
                        throw new RuntimeException('Monday no completó la búsqueda COS.', 0, $response);
                    }
                    $response->throw();
                    $part = $response->json();
                    if (! empty($part['errors']) || ! is_array($part['data'] ?? null)) {
                        throw new RuntimeException('Monday no completó la búsqueda COS; no se cachea como cliente ausente.');
                    }
                    $data['data'] = array_replace($data['data'], $part['data']);
                }
            }
            foreach ($ids as $index => $id) {
                if (! is_array($data['data']["b{$index}"]['items'] ?? null)) {
                    throw new RuntimeException('Monday devolvió una búsqueda COS incompleta.');
                }
            }
            Log::info('COS búsqueda Monday', [
                'boards' => count($ids), 'http_requests' => count($fields), 'mode' => 'parallel',
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            ]);

            // Keep the configured priority regardless of response key order.
            foreach ($ids as $index => $id) {
                $item = $data['data']["b{$index}"]['items'][0] ?? null;
                if (! empty($item['id'])) {
                    return ['item' => $item];
                }
            }

            return ['item' => null];
        });

        if ($item = $result['item']) {
            $user->monday_id = $item['id'];
            $user->save();
        }

        return $item;
    }
}
