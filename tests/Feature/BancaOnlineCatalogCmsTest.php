<?php

namespace Tests\Feature;

use App\Models\Servicio;
use App\Services\BancaOnlineCatalog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BancaOnlineCatalogCmsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.banca_online_cms_test', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        config()->set('database.default', 'banca_online_cms_test');

        DB::purge('banca_online_cms_test');

        Schema::connection('banca_online_cms_test')->create('servicios', function (Blueprint $table) {
            $table->id();
            $table->string('id_hubspot')->unique();
            $table->string('nombre');
            $table->integer('precio')->default(0);
            $table->string('categoria')->default('general');
            $table->string('tipo')->default('servicio');
            $table->text('descripcion_publica')->nullable();
            $table->boolean('activo')->default(true);
            $table->boolean('visible_cliente')->default(false);
            $table->string('moneda', 3)->default('EUR');
            $table->unsignedInteger('orden')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::connection('banca_online_cms_test')->dropIfExists('servicios');
        DB::disconnect('banca_online_cms_test');

        parent::tearDown();
    }

    public function test_selected_components_override_seeded_features_and_fixed_prices(): void
    {
        config()->set('banca_online.category', 'banca_online_2026');

        $component = Servicio::create([
            'id_hubspot' => 'BO2026-COMPONENTE-CMS',
            'nombre' => 'Servicio CMS',
            'precio' => 1200,
            'categoria' => 'banca_online_2026',
            'tipo' => 'servicio',
            'descripcion_publica' => 'Texto publico CMS',
            'activo' => true,
            'moneda' => 'EUR',
            'orden' => 1,
            'metadata' => [
                'record_type' => 'component',
                'country_slug' => 'espana',
                'plan_slug' => 'solicitud-estrategica',
            ],
        ]);

        $package = Servicio::create([
            'id_hubspot' => 'BO2026-PAQUETE-CMS',
            'nombre' => 'Paquete CMS',
            'precio' => 0,
            'categoria' => 'banca_online_2026',
            'tipo' => 'servicio',
            'descripcion_publica' => 'Paquete publico CMS',
            'activo' => true,
            'moneda' => 'EUR',
            'orden' => 2,
            'metadata' => [
                'record_type' => 'package',
                'country_slug' => 'espana',
                'plan_slug' => 'solicitud-estrategica',
                'component_ids' => [$component->id],
                'features' => ['Beneficio sembrado que ya no debe mandar'],
                'list_price' => 9999,
                'price' => 8888,
                'saving' => 1111,
                'discount_type' => 'fixed',
                'discount_value' => 100,
                'show_component_prices' => true,
            ],
        ]);

        $catalog = new BancaOnlineCatalog();
        $freshPackage = $package->fresh();

        $this->assertSame([], $catalog->packageFeatures($freshPackage)->all());
        $this->assertSame([
            [
                'id' => $component->id,
                'name' => 'Servicio CMS',
                'description' => 'Texto publico CMS',
                'price' => 1200.0,
            ],
        ], $catalog->packageDisplayItems($freshPackage)->all());
        $this->assertSame(1200.0, $catalog->packageSubtotal($freshPackage));
        $this->assertSame(100.0, $catalog->packageDiscount($freshPackage));
        $this->assertSame(1100.0, $catalog->packageTotal($freshPackage));
    }

    public function test_sync_preserves_cms_managed_package_content(): void
    {
        config()->set('banca_online', [
            'category' => 'banca_online_2026',
            'source' => 'banca_online_2026',
            'countries' => [
                'espana' => [
                    'label' => 'Espana',
                    'service_name' => 'Espanola Sefardi',
                    'seed_catalog' => true,
                    'public_enabled' => true,
                ],
            ],
            'packages' => [
                'regular' => [
                    'title' => 'Regular',
                    'summary' => 'Resumen semilla',
                    'recommended' => false,
                    'order' => 1,
                ],
            ],
            'plans' => [
                'plan-demo' => [
                    'enabled' => true,
                    'title' => 'Plan demo',
                    'short_title' => 'Demo',
                    'summary' => 'Plan semilla',
                    'service_scope' => ['espana'],
                    'packages' => [
                        'regular' => [
                            'title' => 'Paquete semilla',
                            'summary' => 'Resumen paquete semilla',
                            'list_price' => 500,
                            'price' => 400,
                            'saving' => 100,
                            'show_component_prices' => false,
                            'features' => ['Beneficio semilla'],
                        ],
                    ],
                    'sections' => [],
                ],
            ],
        ]);

        $catalog = new BancaOnlineCatalog();
        $catalog->syncBaseCatalog();

        $package = Servicio::where('id_hubspot', $catalog->packageHubspotId('espana', 'plan-demo', 'regular'))->firstOrFail();
        $metadata = $package->metadata;
        unset(
            $metadata['features'],
            $metadata['show_component_prices'],
            $metadata['list_price'],
            $metadata['price'],
            $metadata['saving']
        );

        $package->forceFill([
            'nombre' => 'Paquete editado en CMS',
            'descripcion_publica' => 'Resumen editado en CMS',
            'metadata' => array_merge($metadata, [
                'cms_managed' => true,
                'component_ids' => [777],
                'discount_type' => 'fixed',
                'discount_value' => 50,
                'tier_title' => 'Paquete editado en CMS',
                'tier_summary' => 'Resumen editado en CMS',
            ]),
        ])->save();

        $catalog->syncBaseCatalog();

        $freshPackage = Servicio::where('id_hubspot', $catalog->packageHubspotId('espana', 'plan-demo', 'regular'))->firstOrFail();
        $freshMetadata = $freshPackage->metadata;

        $this->assertSame('Paquete editado en CMS', $freshPackage->nombre);
        $this->assertSame('Resumen editado en CMS', $freshPackage->descripcion_publica);
        $this->assertTrue($freshMetadata['cms_managed']);
        $this->assertSame([777], $freshMetadata['component_ids']);
        $this->assertArrayNotHasKey('features', $freshMetadata);
        $this->assertArrayNotHasKey('list_price', $freshMetadata);
        $this->assertArrayNotHasKey('price', $freshMetadata);
        $this->assertArrayNotHasKey('saving', $freshMetadata);
    }
}
