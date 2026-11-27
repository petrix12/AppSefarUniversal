<?php

namespace Tests\Feature;

use App\Http\Middleware\AuditRequests;
use App\Mail\ClaveGeneradaMail;
use App\Models\{Compras, Servicio, User};
use App\Services\{GoogleAuthenticationService, HubspotService, MondayRegistrationService};
use Carbon\Carbon;
use GuzzleHttp\{Client, HandlerStack, Middleware};
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Auth, Http, Mail, Schema};
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class GoogleAuthenticationTest extends TestCase
{
    use \Tests\Concerns\RegistrationSchema;

    private MockHandler $transport;
    private array $oauthRequests = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'session.driver' => 'array', 'services.google.client_id' => 'google-client-test',
            'services.google.client_secret' => 'google-secret-test', 'services.google.redirect' => 'https://app.example.test/registerv2/google/callback',
            'app.key' => 'base64:'.base64_encode(str_repeat('g', 32))]);
        Carbon::setTestNow(Carbon::parse('2026-11-27T12:00:00Z'));
        Http::preventStrayRequests(); Mail::fake(); $this->withoutMiddleware(AuditRequests::class);
        $this->createRegistrationSchema(); Permission::findOrCreate('cliente');
        $this->artisan('migrate', ['--path' => [
            'database/migrations/2014_10_12_200000_add_two_factor_columns_to_users_table.php',
            'database/migrations/2026_06_16_100000_add_sales_catalog_fields_to_servicios_table.php',
            'database/migrations/2026_11_27_150000_add_temporary_prices_to_servicios_table.php',
            'database/migrations/2026_11_27_170000_add_google_id_to_users_table.php',
        ]])->assertExitCode(0);
        Schema::table('users', fn (Blueprint $table) => $table->timestamp('two_factor_confirmed_at')->nullable());
        $this->transport = new MockHandler;
        $stack = HandlerStack::create($this->transport); $stack->push(Middleware::history($this->oauthRequests));
        $http = new Client(['handler' => $stack]);
        // Each HTTP request gets its own provider, as in the deployed PHP application.
        Socialite::shouldReceive('driver')->with('google')->andReturnUsing(fn () =>
            (new GoogleProvider(request(), config('services.google.client_id'), config('services.google.client_secret'),
                config('services.google.redirect')))->setHttpClient($http));
    }

    protected function tearDown(): void { Carbon::setTestNow(); parent::tearDown(); }

    private function profile(array $overrides = []): array
    {
        return array_replace(['sub' => '123456789', 'email' => 'person@gmail.com', 'email_verified' => true,
            'name' => 'Persona Ficticia', 'given_name' => 'Persona', 'family_name' => 'Ficticia'], $overrides);
    }

    private function googleCallback(array $profile = [], ?string $state = null)
    {
        $this->transport->append(new Response(200, [], json_encode(['access_token' => 'secret-access-token',
            'refresh_token' => 'secret-refresh-token', 'expires_in' => 3600, 'scope' => 'openid profile email'])),
            new Response(200, [], json_encode($this->profile($profile))));
        return $this->get(route('register.v2.google.callback', ['code' => 'synthetic-code', 'state' => $state ?? session('state')]));
    }

    private function registrationService(): Servicio
    {
        return Servicio::create(['nombre' => 'Nacionalidad Italiana', 'id_hubspot' => 'Italiana', 'precio' => 299,
            'precio_temporal' => 49, 'precio_temporal_desde' => '2026-11-27', 'precio_temporal_hasta' => '2026-12-05']);
    }

    private function registrationInput(array $overrides = []): array
    {
        return array_replace(['nombres' => 'Persona', 'apellidos' => 'Ficticia', 'email' => 'person@gmail.com',
            'passport' => 'TEST-GOOGLE', 'phone' => '000000000', 'pais_de_nacimiento' => 'Pais de prueba',
            'servicio' => 'Italiana', 'rol' => 'cliente', 'pay' => '0', 'google_registration' => 1], $overrides);
    }

    public function test_login_and_registration_offer_google_only_when_credentials_are_configured(): void
    {
        $this->get('/login')->assertOk()->assertSee('Entrar con Google')->assertDontSee('google-secret-test');
        $this->get('/registerv2')->assertOk()->assertSee('Continuar con Google')->assertDontSee('google-secret-test');
        config(['services.google.client_secret' => null]);
        $this->get('/login')->assertOk()->assertDontSee('Entrar con Google');
        $this->get('/registerv2')->assertOk()->assertDontSee('Continuar con Google');
        $this->get(route('login.google.redirect'))->assertRedirect(route('login'))->assertSessionHasErrors('google');
    }

    public function test_authorization_uses_state_and_only_identity_scopes_and_preserves_the_selected_service(): void
    {
        $response = $this->get(route('register.v2.google.redirect', ['servicio' => 'Italiana', 'pay' => 1, 'rol' => 'Administrador',
            'cantidad_alzada' => 2, 'google_id' => 'forged', 'redirect' => 'https://attacker.test']));
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->assertSame('openid profile email', $query['scope']);
        $this->assertSame('google-client-test', $query['client_id']);
        $this->assertSame(config('services.google.redirect'), $query['redirect_uri']);
        $this->assertSame('select_account', $query['prompt']);
        $this->assertNotEmpty($query['state']); $this->assertSame(session('state'), $query['state']);
        $this->assertSame(['servicio' => 'Italiana', 'cantidad_alzada' => '2'], session('google_oauth_flow.context'));
        $this->assertSame([], $this->oauthRequests);
    }

    public function test_existing_verified_gmail_account_is_linked_and_logged_in_without_changing_its_roles_or_payments(): void
    {
        $user = User::factory()->create(['email' => 'PERSON@gmail.com', 'pay' => 2, 'contrato' => 1]);
        $roles = $user->getRoleNames()->all();
        $this->get(route('login.google.redirect'))->assertRedirect();
        $oldSessionId = session()->getId();
        $this->googleCallback()->assertRedirect('/');
        $this->assertAuthenticatedAs($user); $this->assertNotSame($oldSessionId, session()->getId());
        $this->assertSame('123456789', $user->fresh()->googleIdentity->google_id);
        $this->assertSame($roles, $user->fresh()->getRoleNames()->all()); $this->assertSame(2, (int) $user->fresh()->pay);
        $this->assertDatabaseCount('users', 1); $this->assertDatabaseCount('compras', 0);
        $this->assertArrayNotHasKey('google_id', $user->fresh()->toArray());
        $this->assertArrayNotHasKey('google_identity', $user->fresh()->load('googleIdentity')->toArray());
        $this->assertFalse(Schema::hasColumn('users', 'google_id'));
        $this->assertDatabaseHas('user_google_identities', ['user_id' => $user->id, 'google_id' => '123456789']);
    }

    public function test_workspace_email_can_link_an_existing_account_without_creating_a_duplicate(): void
    {
        $user = User::factory()->create(['email' => 'person@example.test']);
        $this->get(route('login.google.redirect'));
        $this->googleCallback(['email' => $user->email, 'hd' => 'example.test'])->assertRedirect('/');
        $this->assertAuthenticatedAs($user); $this->assertSame('123456789', $user->fresh()->googleIdentity->google_id);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_a_linked_google_subject_keeps_the_same_account_even_if_google_changes_its_email(): void
    {
        $user = User::factory()->create(['email' => 'old@example.test']); $user->googleIdentity()->create(['google_id' => '123456789']);
        $this->get(route('login.google.redirect'));
        $this->googleCallback(['email' => 'new@gmail.com'])->assertRedirect('/');
        $this->assertAuthenticatedAs($user); $this->assertSame('old@example.test', $user->fresh()->email);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_a_different_google_subject_cannot_take_an_account_with_the_same_email(): void
    {
        $user = User::factory()->create(['email' => 'person@gmail.com']); $user->googleIdentity()->create(['google_id' => 'different-subject']);
        $this->get(route('login.google.redirect'));
        $this->googleCallback()->assertRedirect(route('login'))->assertSessionHasErrors('google');
        $this->assertGuest(); $this->assertSame('different-subject', $user->fresh()->googleIdentity->google_id);
    }

    #[DataProvider('invalidProfiles')]
    public function test_missing_or_unverified_identity_cannot_sign_in_or_start_registration(array $profile): void
    {
        $this->get(route('login.google.redirect'));
        $this->googleCallback($profile)->assertRedirect(route('login'))->assertSessionHasErrors('google');
        $this->assertGuest(); $this->assertDatabaseCount('users', 0); $this->assertNull(session(GoogleAuthenticationService::REGISTRATION));
    }

    public static function invalidProfiles(): array
    {
        return [[['email_verified' => false]], [['email_verified' => null]], [['email' => '']], [['sub' => '']], [['email' => 'not-an-email']]];
    }

    public function test_invalid_state_is_rejected_before_contacting_google_and_a_callback_cannot_be_replayed(): void
    {
        $this->get(route('login.google.redirect'));
        $this->googleCallback([], 'forged-state')->assertRedirect(route('login'))->assertSessionHasErrors('google');
        $this->assertSame([], $this->oauthRequests); $this->assertGuest();
        $this->get(route('register.v2.google.callback', ['code' => 'synthetic-code', 'state' => 'forged-state']))
            ->assertRedirect(route('login'))->assertSessionHasErrors('google');
        $this->assertSame([], $this->oauthRequests);
    }

    public function test_cancelling_google_returns_to_the_original_registration_without_erasing_the_service(): void
    {
        $this->get(route('register.v2.google.redirect', ['servicio' => 'Italiana']));
        $this->get(route('register.v2.google.callback', ['error' => 'access_denied', 'state' => session('state')]))
            ->assertRedirect(route('register.v2.form', ['servicio' => 'Italiana']))->assertSessionHasErrors('google');
        $this->assertSame([], $this->oauthRequests); $this->assertGuest();
    }

    public function test_unknown_google_identity_prefills_a_persistent_verified_registration_without_creating_a_user(): void
    {
        $this->registrationService();
        $this->get(route('register.v2.google.redirect', ['servicio' => 'Italiana']));
        $this->googleCallback()->assertRedirect(route('register.v2.form', ['servicio' => 'Italiana']));
        $this->assertGuest(); $this->assertDatabaseCount('users', 0); $this->assertDatabaseCount('compras', 0);
        for ($i = 0; $i < 2; $i++) {
            $this->get(route('register.v2.form', ['servicio' => 'Italiana']))->assertOk()->assertSee('Google verificado:')
                ->assertSee('person@gmail.com')->assertSee('readonly', false)->assertDontSee('secret-access-token')->assertDontSee('secret-refresh-token');
        }
        $this->assertStringNotContainsString('secret-access-token', json_encode(session(GoogleAuthenticationService::REGISTRATION)));
        $this->assertSame('123456789', session(GoogleAuthenticationService::REGISTRATION.'.subject'));
    }

    public function test_unknown_login_can_choose_a_service_when_it_has_no_registration_link(): void
    {
        $this->registrationService(); $this->get(route('login.google.redirect'));
        $this->googleCallback()->assertRedirect(route('register.v2.form'));
        $this->get(route('register.v2.form'))->assertOk()->assertSee('Selecciona un servicio')->assertSee('Nacionalidad Italiana');
    }

    public function test_finishing_google_registration_links_the_user_creates_one_purchase_and_signs_in_as_client(): void
    {
        $this->registrationService();
        $this->mock(HubspotService::class)->shouldReceive('searchContactByEmail')->once()->andReturn(['id' => '123']);
        $this->mock(MondayRegistrationService::class)->shouldNotReceive('syncAfterPayment');
        $this->get(route('register.v2.google.redirect', ['servicio' => 'Italiana'])); $this->googleCallback();
        $this->post('/registerv2', $this->registrationInput(['rol' => 'Administrador', 'pay' => '1', 'monto' => 0, 'google_id' => 'forged']))
            ->assertRedirect(route('inicio'));
        $user = User::sole(); $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->hasRole('Cliente')); $this->assertFalse($user->hasRole('Administrador'));
        $this->assertSame('123456789', $user->googleIdentity->google_id); $this->assertSame(0, (int) $user->pay);
        $this->assertSame(49.0, Compras::sole()->monto); $this->assertSame(0, Compras::sole()->pagado);
        $this->assertDatabaseCount('facturas', 0); $this->assertNull(session(GoogleAuthenticationService::REGISTRATION));
        Mail::assertNotSent(ClaveGeneradaMail::class); Http::assertNothingSent();
    }

    public function test_google_registration_cannot_change_the_verified_email_or_use_an_expired_proof(): void
    {
        $this->registrationService(); $this->get(route('register.v2.google.redirect')); $this->googleCallback();
        $this->post('/registerv2', $this->registrationInput(['email' => 'someone@gmail.com']))->assertSessionHasErrors('email');
        $this->assertDatabaseCount('users', 0);
        Carbon::setTestNow(now()->addMinutes(31));
        $this->post('/registerv2', $this->registrationInput())->assertSessionHasErrors('google');
        $this->assertDatabaseCount('users', 0); $this->assertDatabaseCount('compras', 0);
    }

    public function test_a_forged_registration_flag_and_google_subject_cannot_link_a_google_identity(): void
    {
        $this->registrationService();
        $this->post('/registerv2', $this->registrationInput(['google_id' => 'forged']))->assertSessionHasErrors('google');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_google_registration_cannot_replace_an_existing_passport_owners_service_or_payment_status(): void
    {
        $this->registrationService();
        $owner = User::factory()->create(['email' => 'owner@gmail.com', 'passport' => 'TEST-GOOGLE', 'pay' => 2, 'servicio' => 'Portuguesa Sefardi']);
        $this->get(route('register.v2.google.redirect')); $this->googleCallback();
        $this->post('/registerv2', $this->registrationInput())->assertSessionHasErrors('passport');
        $this->assertSame('Portuguesa Sefardi', $owner->fresh()->servicio); $this->assertSame(2, (int) $owner->fresh()->pay);
        $this->assertNull($owner->fresh()->googleIdentity); $this->assertGuest(); $this->assertDatabaseCount('users', 1);
    }

    public function test_existing_third_party_email_requires_the_app_password_before_linking_google(): void
    {
        $user = User::factory()->create(['email' => 'person@example.test']);
        $this->get(route('login.google.redirect'));
        $this->googleCallback(['email' => $user->email])->assertRedirect(route('login'));
        $this->assertGuest(); $this->assertNull($user->fresh()->googleIdentity);
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])->assertSessionHasErrors('email');
        $this->assertNull($user->fresh()->googleIdentity);
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/');
        $this->assertAuthenticatedAs($user); $this->assertSame('123456789', $user->fresh()->googleIdentity->google_id);
    }

    public function test_google_login_requires_the_existing_second_factor_before_authenticating(): void
    {
        $user = User::factory()->create(['email' => 'person@gmail.com', 'two_factor_secret' => encrypt('secret'),
            'two_factor_confirmed_at' => now(), 'two_factor_recovery_codes' => encrypt(json_encode(['recovery-test']))]);
        $this->get(route('login.google.redirect'));
        $this->googleCallback()->assertRedirect(route('two-factor.login'))->assertSessionHas('login.id', $user->id);
        $this->assertGuest();
        $this->post('/two-factor-challenge', ['recovery_code' => 'recovery-test'])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_third_party_google_account_is_linked_only_after_the_password_and_second_factor_succeed(): void
    {
        $user = User::factory()->create(['email' => 'person@example.test', 'two_factor_secret' => encrypt('secret'),
            'two_factor_confirmed_at' => now(), 'two_factor_recovery_codes' => encrypt(json_encode(['link-recovery']))]);
        $this->get(route('login.google.redirect')); $this->googleCallback(['email' => $user->email]);
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('two-factor.login'));
        $this->assertGuest(); $this->assertNull($user->fresh()->googleIdentity);
        $this->post('/two-factor-challenge', ['recovery_code' => 'link-recovery'])->assertRedirect('/');
        $this->assertAuthenticatedAs($user); $this->assertSame('123456789', $user->fresh()->googleIdentity->google_id);
    }

    public function test_a_linking_failure_does_not_leave_an_unlinked_google_registration_or_purchase(): void
    {
        $this->registrationService(); $this->get(route('register.v2.google.redirect')); $this->googleCallback();
        $this->partialMock(GoogleAuthenticationService::class)->shouldReceive('attach')->once()
            ->andThrow(\Illuminate\Validation\ValidationException::withMessages(['google' => 'La vinculación necesita revisión.']));
        $this->post('/registerv2', $this->registrationInput())->assertSessionHasErrors('google');
        $this->assertDatabaseCount('users', 0); $this->assertDatabaseCount('compras', 0);
        $this->assertNotNull(session(GoogleAuthenticationService::REGISTRATION));
    }

    public function test_a_purchase_failure_rolls_back_the_google_identity_together_with_the_new_user(): void
    {
        $this->registrationService(); $this->get(route('register.v2.google.redirect')); $this->googleCallback();
        \Illuminate\Support\Facades\Event::listen('eloquent.creating: '.Compras::class,
            fn () => throw \Illuminate\Validation\ValidationException::withMessages(['google' => 'No se pudo crear la compra.']));

        $this->post('/registerv2', $this->registrationInput())->assertSessionHasErrors('google');

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('user_google_identities', 0);
        $this->assertDatabaseCount('compras', 0);
        $this->assertGuest();
    }

    public function test_oauth_codes_and_state_are_not_saved_in_the_authenticated_request_audit(): void
    {
        $this->artisan('migrate', ['--path' => 'database/migrations/2026_04_14_084509_create_request_audits_table.php'])->assertExitCode(0);
        $this->withMiddleware(AuditRequests::class);
        User::factory()->create(['email' => 'person@gmail.com']);
        $this->get(route('login.google.redirect')); $state = session('state');
        $this->googleCallback()->assertRedirect('/');
        $url = \App\Models\RequestAudit::where('route_name', 'register.v2.google.callback')->sole()->url;
        $this->assertStringNotContainsString('synthetic-code', $url); $this->assertStringNotContainsString($state, $url);
        $this->assertStringNotContainsString('code=', $url); $this->assertStringNotContainsString('state=', $url);
    }

    public function test_the_configuration_check_reports_readiness_without_printing_the_client_secret(): void
    {
        config(['services.google.redirect' => route('register.v2.google.callback')]);
        $this->artisan('google:check')->expectsOutput('GOOGLE_CLIENT_ID: configurado')
            ->expectsOutput('GOOGLE_CLIENT_SECRET: configurado')->assertExitCode(0);
        config(['services.google.client_secret' => null]);
        $this->artisan('google:check')->expectsOutput('GOOGLE_CLIENT_SECRET: pendiente')->assertExitCode(1);
    }

    public function test_using_another_email_clears_google_proof_and_keeps_the_selected_service(): void
    {
        $this->get(route('register.v2.google.redirect', ['servicio' => 'Italiana'])); $this->googleCallback();
        $this->get(route('register.v2.google.cancel', ['servicio' => 'Italiana']))
            ->assertRedirect(route('register.v2.form', ['servicio' => 'Italiana']));
        $this->assertNull(session(GoogleAuthenticationService::REGISTRATION)); $this->assertGuest();
    }
}
