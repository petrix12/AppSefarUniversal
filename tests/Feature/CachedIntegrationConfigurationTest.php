<?php

namespace Tests\Feature;

use App\Http\Controllers\UserController;
use App\Http\Controllers\BancaOnlineController;
use App\Services\ClientSupportTicketService;
use App\Services\HubspotService;
use Dotenv\Repository\RepositoryBuilder;
use Illuminate\Support\Env;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class CachedIntegrationConfigurationTest extends TestCase
{
    private $environmentRepository;

    public function createApplication()
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->useEnvironmentPath('/dev')->loadEnvironmentFrom('null');
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.hubspot.key' => 'cached-hubspot-test-token',
            'services.hubspot.force_ip_resolve' => 'v6',
            'services.hubspot.support_tickets.pipeline' => 'pipeline-test',
            'services.hubspot.support_tickets.stage' => 'stage-test',
            'services.hubspot.support_tickets.priority' => 'HIGH',
            'services.stripe.key' => 'pk_test_default',
            'services.stripe.secret' => 'sk_test_default',
            'services.teamleader.client_id' => 'cached-teamleader-test-id',
            'services.teamleader.client_secret' => 'cached-teamleader-test-secret',
            'services.teamleader.redirect' => 'https://example.test/teamleader/callback',
        ]);
        // A fresh worker using config:cache does not load values from .env.
        $repository = new \ReflectionProperty(Env::class, 'repository');
        $this->environmentRepository = Env::getRepository();
        $repository->setValue(null, RepositoryBuilder::createWithNoAdapters()->make());
    }

    protected function tearDown(): void
    {
        (new \ReflectionProperty(Env::class, 'repository'))->setValue(null, $this->environmentRepository);
        parent::tearDown();
    }

    public function test_hubspot_uses_configured_token_when_environment_is_not_loaded(): void
    {
        $this->assertNull(env('HUBSPOT_KEY'));
        $service = new HubspotService;
        $client = (new \ReflectionProperty(HubspotService::class, 'hubspot'))->getValue($service);
        $configuration = (new \ReflectionProperty(\HubSpot\Discovery\DiscoveryBase::class, 'config'))->getValue($client);
        $this->assertSame('cached-hubspot-test-token', $configuration->getAccessToken());
    }

    public function test_user_controller_can_be_resolved_without_dotenv(): void
    {
        $this->assertInstanceOf(UserController::class, app(UserController::class));
    }

    public function test_transport_options_are_preserved_without_dotenv(): void
    {
        $service = new HubspotService;
        $sdk = (new \ReflectionProperty(HubspotService::class, 'hubspot'))->getValue($service);
        $http = (new \ReflectionProperty(\HubSpot\Discovery\DiscoveryBase::class, 'client'))->getValue($sdk);
        $this->assertSame('v6', $http->getConfig('force_ip_resolve'));
        $this->assertSame(30, $http->getConfig('timeout'));
    }

    public function test_payments_use_only_the_primary_account_without_dotenv(): void
    {
        $controller = app(BancaOnlineController::class);
        $publicKey = new \ReflectionMethod($controller, 'stripePublicKey');
        $secretKey = new \ReflectionMethod($controller, 'stripeSecretKey');
        $this->assertSame('pk_test_default', $publicKey->invoke($controller));
        $this->assertSame('sk_test_default', $secretKey->invoke($controller));
        $catalog = app(\App\Services\BancaOnlineCatalog::class);
        foreach (['espana', 'portugal', 'italia'] as $country) {
            $this->assertArrayNotHasKey('stripe_account', $catalog->country($country));
        }
    }

    public function test_ticket_routing_uses_cached_pipeline_stage_and_priority(): void
    {
        $hubspot = $this->mock(HubspotService::class);
        $hubspot->shouldReceive('getTicketPipelineStage')->once()->with('pipeline-test', 'stage-test')
            ->andReturn(['hs_pipeline' => 'pipeline-test', 'hs_pipeline_stage' => 'stage-test']);
        $hubspot->shouldReceive('createTicket')->once()->with([
            'subject' => 'Test', 'content' => 'Test content', 'hs_pipeline' => 'pipeline-test',
            'hs_pipeline_stage' => 'stage-test', 'hs_ticket_priority' => 'HIGH',
        ], '10')->andReturn(['id' => '20']);
        $service = app(ClientSupportTicketService::class);
        $method = new \ReflectionMethod($service, 'createHubspotTicket');
        $this->assertSame(['id' => '20'], $method->invoke($service, 'Test', 'Test content', '10'));
    }

    public function test_fresh_http_worker_boots_from_cached_config_and_ignores_dotenv(): void
    {
        $directory = sys_get_temp_dir().'/sefar-cached-config-'.bin2hex(random_bytes(8));
        $filesystem = new Filesystem;
        $filesystem->makeDirectory($directory.'/cache', 0700, true);
        $filesystem->makeDirectory($directory.'/views', 0700);

        // Use only synthetic credentials in the disposable cached configuration.
        $sanitize = function (array $values, string $path = '') use (&$sanitize): array {
            foreach ($values as $key => $value) {
                $field = ltrim($path.'.'.$key, '.');
                if (is_array($value)) $values[$key] = $sanitize($value, $field);
                elseif ($field !== 'permission.cache.key' && is_string($key) && preg_match('/secret|password|token|api_key|client_id|private_key|^key$/i', $key)) $values[$key] = null;
            }
            return $values;
        };
        $configuration = $sanitize(config()->all());
        $configuration['app']['env'] = 'testing';
        $configuration['app']['debug'] = true;
        $configuration['app']['key'] = 'base64:'.base64_encode(str_repeat('k', 32));
        $configuration['database']['default'] = 'sqlite';
        $configuration['database']['connections']['sqlite']['database'] = ':memory:';
        $configuration['session']['driver'] = 'array';
        $configuration['cache']['default'] = 'array';
        $configuration['view']['compiled'] = $directory.'/views';
        $configuration['logging']['default'] = 'single';
        $configuration['logging']['channels']['single']['path'] = $directory.'/errors.log';
        $configuration['services']['hubspot']['key'] = 'cached-worker-test-token';
        $configuration['services']['teamleader']['client_id'] = 'cached-client';
        $configuration['services']['teamleader']['client_secret'] = 'cached-secret';
        $filesystem->put($directory.'/cache/config.php', '<?php return '.var_export($configuration, true).';');
        $filesystem->put($directory.'/.env', 'HUBSPOT_KEY=this-file-must-not-be-loaded');

        $source = 'require '.var_export(base_path('vendor/autoload.php'), true).';'
            .'$repository = new ReflectionProperty(Illuminate\\Support\\Env::class, "repository");'
            .'$repository->setValue(null, Dotenv\\Repository\\RepositoryBuilder::createWithNoAdapters()->make());'
            .'unset($_ENV["APP_BASE_PATH"]);'
            .'$app = require '.var_export(base_path('bootstrap/app.php'), true).';'
            .'$app->useBootstrapPath('.var_export($directory, true).');'
            .'$app->useEnvironmentPath('.var_export($directory, true).');'
            .'$kernel = $app->make(Illuminate\\Contracts\\Http\\Kernel::class);'
            .'$response = $kernel->handle(Illuminate\\Http\\Request::create("https://example.test/login", "GET"));'
            .'$controller = $app->make(App\\Http\\Controllers\\UserController::class);'
            .'echo json_encode(["status" => $response->getStatusCode(), "cached" => $app->configurationIsCached(),'
            .'"env_absent" => env("HUBSPOT_KEY") === null, "controller" => $controller instanceof App\\Http\\Controllers\\UserController]);';
        try {
            $process = new Process([PHP_BINARY, '-r', $source], base_path());
            $process->setTimeout(30);
            $process->run();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
            $result = json_decode($process->getOutput(), true);
            $diagnostic = is_file($directory.'/errors.log') ? substr(file_get_contents($directory.'/errors.log'), 0, 2500) : $process->getOutput();
            $this->assertSame(200, $result['status'] ?? null, $diagnostic);
            $this->assertSame(['status' => 200, 'cached' => true, 'env_absent' => true, 'controller' => true], $result);
        } finally {
            $filesystem->deleteDirectory($directory);
        }
    }
}
