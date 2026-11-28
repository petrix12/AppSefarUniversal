<?php

namespace Tests\Feature;

use App\Models\{MarketingTemplate, User};
use App\Services\{EmailTemplateGenerator, MarketingEmailRenderer};
use Illuminate\Support\Facades\{Http, Mail};
use Tests\TestCase;

class EmailTemplateGeneratorTest extends TestCase
{
    use \Tests\Concerns\RegistrationSchema;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'session.driver' => 'array', 'services.openrouter.key' => 'private-test-key']);
        Http::preventStrayRequests(); Mail::fake(); $this->createRegistrationSchema();
        $this->artisan('migrate', ['--path' => [
            'database/migrations/2026_05_07_100000_create_settings_table.php',
            'database/migrations/2026_09_29_120000_create_marketing_campaign_tables.php',
            'database/migrations/2026_10_09_120000_create_ai_process_settings_table.php',
        ]])->assertExitCode(0);
    }

    private function admin(): User
    {
        \Spatie\Permission\Models\Role::findOrCreate('Administrador');
        \Spatie\Permission\Models\Permission::findOrCreate('administrador');
        return tap(User::factory()->create(), function ($user) {
            $user->syncRoles(['Administrador']);
            $user->givePermissionTo('administrador');
        });
    }

    private function draft(): array
    {
        return ['name' => 'Pago de registro', 'subject' => 'Recuerda tu registro', 'preheader' => 'Tu siguiente paso',
            'headline' => 'Hola, cliente', 'paragraphs' => ['Acompañamos a nuestros clientes.', '<script>alert(1)</script>Texto editable.'],
            'highlight' => 'Tu historia familiar abre posibilidades.', 'points' => ['Revisa tu registro.'],
            'button_label' => 'Entrar a la app', 'closing' => 'Atención al Representado'];
    }

    public function test_ai_generates_editable_branded_content_without_saving_or_sending_and_ignores_model_urls(): void
    {
        Http::fake(['openrouter.ai/*' => Http::response(['choices' => [['message' => ['content' => json_encode($this->draft() + ['url' => 'https://untrusted.test'])]]]])]);
        $result = $this->actingAs($this->admin())->postJson(route('marketing.templates.generate'), [
            'prompt' => 'Recordar el pago del registro de forma breve.', 'action_url' => 'https://app.sefaruniversal.com',
        ])->assertOk()->assertJsonPath('name', 'Pago de registro');
        $html = $result->json('body_html');
        $this->assertStringContainsString('Hola, representado', $html);
        $this->assertStringContainsString('representados', $html);
        $this->assertStringContainsString('contenteditable="true"', $html);
        $this->assertStringContainsString('#093143', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('untrusted.test', $html);
        $this->assertDatabaseCount('marketing_templates', 0); Mail::assertNothingSent();
        Http::assertSent(fn ($request) => str_contains($request['messages'][0]['content'], 'Sefar Universal')
            && str_contains($request['messages'][0]['content'], 'representado') && $request['response_format']['type'] === 'json_object');
    }

    public function test_generated_templates_can_be_saved_reopened_and_edited(): void
    {
        $this->actingAs($this->admin())->post(route('marketing.templates.store'), ['name' => 'Original', 'subject' => 'Asunto original',
            'body_html' => '<style>@media(max-width:600px){p{font-size:16px}}</style><p contenteditable="true">Texto original</p>'])
            ->assertSessionHasNoErrors();
        $template = MarketingTemplate::sole();
        $this->assertStringContainsString('@media(max-width:600px)', $template->body_html);
        $this->get(route('marketing.templates.edit', $template))->assertOk()->assertSee('Original')->assertSee('Asunto original')->assertDontSee('private-test-key');
        $this->put(route('marketing.templates.update', $template), ['name' => 'Actualizada', 'subject' => 'Asunto editado', 'body_html' => '<p>Texto editado</p>'])
            ->assertRedirect(route('marketing.templates.edit', $template));
        $this->assertSame('Texto editado', $template->fresh()->body_text);
        $this->assertSame(1, MarketingTemplate::count());
    }

    public function test_editor_preview_uses_the_shared_responsive_shell_and_plain_text_excludes_css(): void
    {
        $this->actingAs($this->admin())->get(route('marketing.templates.index'))->assertOk()
            ->assertSee('Crear un correo con IA')->assertSee('Vista móvil')->assertSee('max-width:600px')
            ->assertSee('%%SEFAR_EMAIL_CONTENT%%');
        $this->assertSame('Mensaje', app(MarketingEmailRenderer::class)->plainText('<style>.x{color:red}</style><p>Mensaje</p>'));
    }

    public function test_failures_and_missing_credentials_never_store_an_incomplete_template(): void
    {
        $this->actingAs($this->admin());
        Http::fake(['openrouter.ai/*' => Http::response(['error' => 'private-test-key'], 429)]);
        $this->postJson(route('marketing.templates.generate'), ['prompt' => 'Un correo de seguimiento.'])->assertUnprocessable()->assertDontSee('private-test-key');
        config(['services.openrouter.key' => null]);
        $this->postJson(route('marketing.templates.generate'), ['prompt' => 'Un correo de seguimiento.'])->assertUnprocessable();
        $this->assertDatabaseCount('marketing_templates', 0); Mail::assertNothingSent();
    }

    public function test_generation_and_editing_require_an_internal_administrator(): void
    {
        $this->postJson(route('marketing.templates.generate'), ['prompt' => 'Un correo de seguimiento.'])->assertUnauthorized();
        $this->actingAs(User::factory()->create())->postJson(route('marketing.templates.generate'), ['prompt' => 'Un correo de seguimiento.'])->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_an_invalid_model_response_and_an_unsafe_action_url_are_rejected(): void
    {
        $this->actingAs($this->admin());
        Http::fake(['openrouter.ai/*' => Http::response(['choices' => [['message' => ['content' => '{"name":"Incompleto"}']]]])]);
        $this->postJson(route('marketing.templates.generate'), ['prompt' => 'Un correo de bienvenida.'])
            ->assertUnprocessable()->assertJsonValidationErrors('prompt');
        $this->postJson(route('marketing.templates.generate'), ['prompt' => 'Un correo de bienvenida.', 'action_url' => 'javascript:alert(1)'])
            ->assertUnprocessable()->assertJsonValidationErrors('action_url');
        $this->assertDatabaseCount('marketing_templates', 0);
    }

    public function test_the_brand_prompt_is_visible_editable_saved_and_used_by_later_generations(): void
    {
        $this->actingAs($admin = $this->admin())->get(route('marketing.templates.index'))->assertOk()
            ->assertSee('Revisar y editar el prompt de marca')->assertSee('Guardar prompt')->assertSee('Restaurar prompt inicial');
        $prompt = 'Presenta a Sefar Universal como autoridad en genealogía. Usa un tono directo y dirige el mensaje al representado.';
        $this->putJson(route('marketing.templates.prompt.update'), ['system_prompt' => $prompt])->assertOk();
        $this->assertDatabaseHas('settings', ['key' => 'app_mail.template_prompt', 'value' => $prompt]);
        $this->assertDatabaseHas('settings', ['key' => 'app_mail.template_prompt_updated_by', 'value' => (string) $admin->id]);
        $this->get(route('marketing.templates.index'))->assertSee($prompt);
        Http::fake(['openrouter.ai/*' => Http::response(['choices' => [['message' => ['content' => json_encode($this->draft())]]]])]);
        $this->postJson(route('marketing.templates.generate'), ['prompt' => 'Un correo de bienvenida.'])->assertOk();
        Http::assertSent(fn ($request) => str_starts_with($request['messages'][0]['content'], $prompt));
        $unsavedPrompt = 'Destaca el acompañamiento de Sefar Universal al representado con una bienvenida cálida.';
        $this->postJson(route('marketing.templates.generate'), ['prompt' => 'Un correo de bienvenida.', 'system_prompt' => $unsavedPrompt])->assertOk();
        Http::assertSent(fn ($request) => str_starts_with($request['messages'][0]['content'], $unsavedPrompt));
        $this->assertSame($prompt, app(EmailTemplateGenerator::class)->savedPrompt());
        Mail::assertNothingSent();
    }

    public function test_only_authorized_staff_can_save_a_valid_prompt(): void
    {
        $this->putJson(route('marketing.templates.prompt.update'), ['system_prompt' => str_repeat('a', 50)])->assertUnauthorized();
        $this->actingAs(User::factory()->create())->putJson(route('marketing.templates.prompt.update'), ['system_prompt' => str_repeat('a', 50)])->assertForbidden();
        $this->actingAs($this->admin())->putJson(route('marketing.templates.prompt.update'), ['system_prompt' => ''])->assertUnprocessable();
        $this->assertDatabaseMissing('settings', ['key' => 'app_mail.template_prompt']);
    }
}
