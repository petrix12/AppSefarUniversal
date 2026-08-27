<?php

namespace Tests\Feature;

use App\Services\MondayCatalogService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class MondayCatalogServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('cache.default', 'array');
        config()->set('services.monday.token', 'monday-test-token');
        Cache::flush();
    }

    public function test_it_loads_and_caches_boards_and_groups_from_monday(): void
    {
        Http::fake(function ($request) {
            $query = $request->data()['query'];

            if (str_contains($query, 'boards(limit: 100, page: $page)')) {
                return Http::response([
                    'data' => [
                        'boards' => [
                            ['id' => '20', 'name' => 'Ventas'],
                            ['id' => '10', 'name' => 'Análisis'],
                        ],
                    ],
                ]);
            }

            return Http::response([
                'data' => [
                    'boards' => [[
                        'groups' => [
                            ['id' => 'en_proceso', 'title' => 'En proceso'],
                            ['id' => 'nuevos', 'title' => 'Nuevos'],
                        ],
                    ]],
                ],
            ]);
        });

        $catalog = app(MondayCatalogService::class);

        $this->assertSame([
            ['id' => '10', 'name' => 'Análisis'],
            ['id' => '20', 'name' => 'Ventas'],
        ], $catalog->boards());
        $this->assertSame([
            ['id' => 'en_proceso', 'name' => 'En proceso'],
            ['id' => 'nuevos', 'name' => 'Nuevos'],
        ], $catalog->groups('10'));

        $catalog->boards();
        $catalog->groups('10');

        Http::assertSentCount(2);
        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'monday-test-token'));
    }

    public function test_it_reports_a_missing_token_without_calling_monday(): void
    {
        config()->set('services.monday.token');
        Http::fake();

        try {
            app(MondayCatalogService::class)->boards();
            $this->fail('Se esperaba una excepción por falta de MONDAY_TOKEN.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Falta configurar MONDAY_TOKEN.', $exception->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_it_loads_and_caches_columns_for_a_selected_board(): void
    {
        Http::fake(function ($request) {
            $query = $request->data()['query'];

            $this->assertStringContainsString('columns {', $query);

            return Http::response([
                'data' => [
                    'boards' => [[
                        'columns' => [
                            ['id' => 'text_1', 'title' => 'Nombre', 'type' => 'text'],
                            ['id' => 'status', 'title' => 'Estado', 'type' => 'status'],
                        ],
                    ]],
                ],
            ]);
        });

        $catalog = app(MondayCatalogService::class);

        $this->assertSame([
            ['id' => 'status', 'name' => 'Estado', 'type' => 'status'],
            ['id' => 'text_1', 'name' => 'Nombre', 'type' => 'text'],
        ], $catalog->columns('10'));

        $catalog->columns('10');

        Http::assertSentCount(1);
    }

    public function test_it_exposes_the_graphql_message_returned_with_an_http_error(): void
    {
        Cache::flush();
        Http::fake([
            'api.monday.com/v2' => Http::response([
                'errors' => [[
                    'message' => 'Invalid value for argument limit.',
                ]],
            ], 400),
        ]);

        try {
            app(MondayCatalogService::class)->boards();
            $this->fail('Se esperaba el error GraphQL de Monday.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Monday rechazó la consulta (HTTP 400): Invalid value for argument limit.',
                $exception->getMessage()
            );
        }
    }
}
