<?php

namespace Tests\Feature;

use App\Http\Middleware\AuditRequests;
use App\Listeners\PreventBlockedClientDelivery;
use App\Models\ClientRestrictionEvent;
use App\Models\ClientRestrictionScan;
use App\Models\ClientRestrictionSync;
use App\Models\File;
use App\Models\User;
use App\Services\ClientBlockPropagationService;
use App\Services\ClientRestrictionService;
use App\Services\TestClientRestrictionScanner;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class ClientRestrictionTest extends TestCase
{
    private User $admin;
    private User $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--path' => [
            'database/migrations/2014_10_12_000000_create_users_table.php',
            'database/migrations/2021_03_08_173429_create_permission_tables.php',
            'database/migrations/2026_09_01_000000_add_contrato_to_users_table.php',
            'database/migrations/2026_09_28_120100_ensure_user_genealogy_tree_links_table.php',
            'database/migrations/2026_09_29_090000_create_user_change_audits_table.php',
            'database/migrations/2026_10_20_120000_add_client_block_fields_to_users_table.php',
            'database/migrations/2026_11_12_120000_add_client_restriction_controls.php',
        ]])->assertExitCode(0);
        Schema::table('users', function (Blueprint $table): void {
            $table->string('tl_id')->nullable();
            $table->string('monday_id')->nullable();
        });
        Schema::create('files', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('user_id')->nullable();
            $table->string('IDCliente')->nullable(); $table->string('location'); $table->string('file'); $table->timestamps();
        });
        foreach (['Administrador', 'Cliente', 'Ventas'] as $role) {
            Role::findOrCreate($role, 'web');
        }
        $this->admin = User::factory()->create()->syncRoles(['Administrador']);
        $this->client = User::factory()->create(['email' => 'client@example.test', 'passport' => 'P123', 'hs_id' => '123']);
        $this->withoutMiddleware(AuditRequests::class);
        Http::preventStrayRequests(); Mail::fake();
        Gate::define('crud.files.index', fn () => true);
        Route::middleware(['web', 'auth'])->get('/restriction-test/{user}', fn (User $user) => response()->json(['name' => $user->name]));
    }

    public function test_retired_deal_blocks_even_when_contact_property_is_empty_and_never_exposes_internal_reason(): void
    {
        $this->assertTrue(app(ClientRestrictionService::class)->detectHubspotRetirement($this->client, ['properties' => []], [
            ['id' => '456', 'properties' => ['el_cliente_se_encuentra_retirado' => 'true', 'motivo_del_retiro' => 'Nota privada de jurídico']],
        ]));
        $block = $this->client->accessBlock()->first();
        $this->assertSame('hubspot', $block->source);
        $this->assertSame('456', $block->source_reference);
        $this->assertSame('Nota privada de jurídico', $block->internal_note);
        $this->assertStringNotContainsString('jurídico', $this->client->clientBlockReasonDescription());
        $this->assertDatabaseCount('client_restriction_events', 1);
        $this->assertDatabaseCount('client_restriction_syncs', 3);

        $this->actingAs($this->client)->get('/')->assertRedirect(route('client-access-blocked'));
        $this->get(route('client-access-blocked'))->assertOk()->assertSee('Registro retirado')->assertDontSee('Nota privada');
        $this->getJson('/api/user')->assertForbidden()->assertDontSee('jurídico');
    }

    public function test_false_or_stale_hubspot_data_never_automatically_releases_a_hold_or_overwrites_a_manual_reason(): void
    {
        $service = app(ClientRestrictionService::class);
        $service->block($this->client, 'other', 'Comunícate con atención al cliente.', actor: $this->admin);
        $this->assertFalse($service->detectHubspotRetirement($this->client, ['properties' => ['retirado' => 'false']], []));
        $this->assertFalse($service->detectHubspotRetirement($this->client, ['properties' => ['retirado' => 'Sí']], []));
        $this->assertSame('other', $this->client->accessBlock()->first()->reason);
        $this->assertDatabaseCount('client_restriction_events', 1);
    }

    public function test_only_admins_and_explicit_reviewers_can_read_restricted_client_records(): void
    {
        $this->block();
        $staff = User::factory()->create()->syncRoles(['Ventas']);
        $this->actingAs($staff)->getJson('/restriction-test/'.$this->client->id)->assertForbidden();
        $this->getJson(route('admin.client-restrictions.index'))->assertForbidden();
        $staff->givePermissionTo('clients.blocks.review');
        $this->getJson('/restriction-test/'.$this->client->id)->assertOk();
        $this->get(route('admin.client-restrictions.index'))->assertOk();
        $this->postJson(route('admin.client-restrictions.scan'))->assertForbidden();
    }

    public function test_files_are_hidden_from_staff_and_downloads_are_blocked_even_for_administrators(): void
    {
        $file = File::create(['user_id' => $this->client->id, 'IDCliente' => 'P123', 'location' => 'clients/P123', 'file' => 'document.pdf']);
        $this->block();
        $staff = User::factory()->create()->syncRoles(['Ventas']);
        $this->actingAs($staff);
        $this->assertSame(0, File::count());
        $this->actingAs($this->admin);
        $this->assertSame(1, File::count());
        $this->getJson('/viewfile/'.$file->id)->assertStatus(423);
        $this->postJson('/openfile', ['path' => 'clients/P123/document.pdf'])->assertStatus(423);
    }

    public function test_export_and_queued_email_recheck_the_block_including_cc_and_bcc(): void
    {
        $this->block();
        $this->actingAs($this->admin);
        $response = $this->get(route('admin.client-exports.download', ['format' => 'csv']))->assertOk();
        $this->assertStringNotContainsString('client@example.test', $response->streamedContent());
        foreach (['to', 'cc', 'bcc'] as $destination) {
            $email = (new Email())->from('company@example.test')->to('other@example.test')->subject('Entrega')->text('Expediente');
            $email->$destination('CLIENT@example.test');
            $this->assertFalse(app(PreventBlockedClientDelivery::class)->mail(new MessageSending($email, [])));
        }
    }

    public function test_scanner_covers_contact_fields_protects_staff_and_does_not_block_until_reviewed(): void
    {
        $test = User::factory()->create(['name' => 'PRUEBA 123', 'passport' => 'P-test-123']);
        $real = User::factory()->create(['name' => 'Testa Persona', 'email' => 'legit@example.org']);
        $employee = User::factory()->create(['name' => 'Usuario prueba'])->syncRoles(['Administrador']);
        $scanner = app(TestClientRestrictionScanner::class);
        $scan = $scanner->preview($this->admin);
        $ids = array_column($scan->candidates, 'id');
        $this->assertContains($test->id, $ids);
        $this->assertNotContains($real->id, $ids);
        $this->assertNotContains($employee->id, $ids);
        $this->assertDatabaseCount('user_access_blocks', 0);
        $this->assertSame(1, $scanner->apply($scan, $this->admin, [$test->id]));
        $this->assertTrue($test->fresh()->hasIndividualClientBlock());
        $this->assertFalse($real->hasIndividualClientBlock());
        $this->assertDatabaseCount('client_restriction_syncs', 3);
    }

    public function test_scanner_requires_fresh_data_and_rejects_injected_ids_without_partial_application(): void
    {
        $test = User::factory()->create(['name' => 'Prueba']);
        $scanner = app(TestClientRestrictionScanner::class);
        $scan = $scanner->preview($this->admin);
        try {
            $scanner->apply($scan, $this->admin, [$test->id, $this->admin->id]);
            $this->fail('Debe rechazar IDs fuera de la selección.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('user_access_blocks', 0);
        }
        $test->update(['name' => 'Persona real']);
        $this->expectException(ValidationException::class);
        $scanner->apply($scan, $this->admin, [$test->id]);
    }

    public function test_preview_is_private_single_use_and_expiring(): void
    {
        $test = User::factory()->create(['name' => 'Prueba']);
        $scanner = app(TestClientRestrictionScanner::class);
        $scan = $scanner->preview($this->admin);
        $scanner->apply($scan, $this->admin, [$test->id]);
        $this->expectException(ValidationException::class);
        $scanner->apply($scan, $this->admin, [$test->id]);
    }

    public function test_the_administration_flow_requires_confirmation_and_has_a_rendered_preview(): void
    {
        $test = User::factory()->create(['name' => 'Prueba de pantalla']);
        $this->actingAs($this->admin)->post(route('admin.client-restrictions.scan'))->assertRedirect();
        $scan = ClientRestrictionScan::sole();
        $this->get(route('admin.client-restrictions.index', ['scan' => $scan->id]))->assertOk()->assertSee('Prueba de pantalla');
        $this->postJson(route('admin.client-restrictions.apply', $scan), ['ids' => [$test->id]])
            ->assertUnprocessable()->assertJsonValidationErrors('confirm');
        $this->assertDatabaseCount('user_access_blocks', 0);
        $this->post(route('admin.client-restrictions.apply', $scan), ['ids' => [$test->id], 'confirm' => 1])->assertRedirect();
        $this->assertTrue($test->hasIndividualClientBlock());
    }

    public function test_unblock_requires_an_admin_and_records_the_resolution_and_latest_sync_version(): void
    {
        $this->block();
        $before = ClientRestrictionSync::first()->version;
        $this->actingAs($this->admin)->postJson(route('admin.client-restrictions.unblock', $this->client), ['explanation' => 'ok'])
            ->assertUnprocessable();
        $this->post(route('admin.client-restrictions.unblock', $this->client), ['explanation' => 'Situación revisada y resuelta con el cliente.'])->assertRedirect();
        $this->assertFalse($this->client->fresh()->hasIndividualClientBlock());
        $this->assertDatabaseHas('client_restriction_events', ['action' => 'unblocked', 'actor_id' => $this->admin->id]);
        $this->assertNotSame($before, ClientRestrictionSync::first()->version);
        $this->assertSame(0, ClientRestrictionSync::where('blocked', true)->count());
    }

    public function test_outbound_platforms_remain_configuration_required_until_configured_and_do_not_release_local_access(): void
    {
        $this->block();
        ClientRestrictionSync::all()->each(fn ($state) => app(ClientBlockPropagationService::class)->sync($state));
        $this->assertSame(3, ClientRestrictionSync::where('status', 'configuration_required')->count());
        $this->assertTrue($this->client->hasIndividualClientBlock());
        Http::assertNothingSent();
    }

    public function test_delivery_endpoint_denies_unknown_or_conflicting_identities(): void
    {
        $controller = app(\App\Http\Controllers\Api\ClientRestrictionApiController::class);
        $this->block();
        foreach ([['email' => $this->client->email], ['email' => 'unknown@example.org'], ['user_id' => $this->client->id, 'email' => 'another@example.org']] as $input) {
            $response = $controller->delivery(\Illuminate\Http\Request::create('/delivery', 'POST', $input));
            $this->assertFalse($response->getData(true)['allowed']);
        }
    }

    public function test_platform_writes_are_acknowledged_as_markers_and_failure_is_kept_for_retry(): void
    {
        $this->block();
        config(['client_restrictions.hubspot.enabled' => true]);
        $hubspot = \Mockery::mock(\App\Services\HubspotService::class);
        $hubspot->shouldReceive('updateContact')->once()->with('123', ['sefar_entregas_bloqueadas' => 'true'])->andReturn([]);
        $this->instance(\App\Services\HubspotService::class, $hubspot);
        $state = ClientRestrictionSync::where('platform', 'hubspot')->first();
        app(ClientBlockPropagationService::class)->sync($state);
        $this->assertSame('marker_synced', $state->fresh()->status);
        $this->assertTrue($this->client->hasIndividualClientBlock());

        $hubspot->shouldReceive('updateContact')->once()->andThrow(new \RuntimeException('HTTP 403: permiso insuficiente'));
        app(ClientBlockPropagationService::class)->sync($state);
        $this->assertSame('failed', $state->fresh()->status);
        $this->assertNotNull($state->fresh()->retry_at);
        $this->assertTrue($this->client->hasIndividualClientBlock());
    }

    public function test_teamleader_uses_additive_tagging_instead_of_overwriting_existing_tags(): void
    {
        $this->client->update(['tl_id' => 'tl-contact']);
        $this->block();
        config(['client_restrictions.teamleader.enabled' => true, 'services.teamleader.min_request_gap_ms' => 0]);
        $service = \Mockery::mock(\App\Services\TeamleaderService::class)->makePartial();
        $service->shouldReceive('getAccessToken')->andReturn('test-token');
        $this->instance(\App\Services\TeamleaderService::class, $service);
        Http::fake(['api.focus.teamleader.eu/contacts.tag' => Http::response(null, 204)]);
        $state = ClientRestrictionSync::where('platform', 'teamleader')->first();
        app(ClientBlockPropagationService::class)->sync($state);
        $this->assertSame('marker_synced', $state->fresh()->status);
        Http::assertSent(fn ($request) => $request['id'] === 'tl-contact' && $request['tags'] === ['SEFAR_ENTREGAS_BLOQUEADAS']);
    }

    public function test_monday_uses_the_linked_item_and_requires_an_acknowledgement(): void
    {
        $this->client->update(['monday_id' => 'item-1']);
        $this->block();
        config(['client_restrictions.monday.enabled' => true]);
        $api = \Mockery::mock(\App\Services\MondayApiClient::class);
        $api->shouldReceive('item')->with('item-1')->andReturn(['board' => ['id' => 'board-1']]);
        $api->shouldReceive('query')->once()->withArgs(fn ($query, $variables) =>
            $variables['item'] === 'item-1' && $variables['board'] === 'board-1' &&
            json_decode($variables['values'], true)['sefar_entregas_bloqueadas']['label'] === 'Bloqueado')
            ->andReturn(['change_multiple_column_values' => ['id' => 'item-1']]);
        $this->instance(\App\Services\MondayApiClient::class, $api);
        $state = ClientRestrictionSync::where('platform', 'monday')->first();
        app(ClientBlockPropagationService::class)->sync($state);
        $this->assertSame('marker_synced', $state->fresh()->status);
    }

    public function test_a_stale_propagation_result_cannot_mark_a_new_unblock_as_synced(): void
    {
        $this->block();
        config(['client_restrictions.hubspot.enabled' => true]);
        $hubspot = \Mockery::mock(\App\Services\HubspotService::class);
        $hubspot->shouldReceive('updateContact')->once()->andReturnUsing(function () {
            app(ClientRestrictionService::class)->unblock($this->client, $this->admin, 'Caso resuelto mientras se sincronizaba.');
            return [];
        });
        $this->instance(\App\Services\HubspotService::class, $hubspot);
        $state = ClientRestrictionSync::where('platform', 'hubspot')->first();
        app(ClientBlockPropagationService::class)->sync($state);
        $this->assertSame('pending', $state->fresh()->status);
        $this->assertFalse($state->fresh()->blocked);
    }

    public function test_expired_previews_and_other_administrators_cannot_apply_a_batch(): void
    {
        $test = User::factory()->create(['name' => 'Prueba']);
        $scanner = app(TestClientRestrictionScanner::class);
        $scan = $scanner->preview($this->admin);
        $otherAdmin = User::factory()->create()->syncRoles(['Administrador']);
        $this->actingAs($otherAdmin)->postJson(route('admin.client-restrictions.apply', $scan), ['ids' => [$test->id], 'confirm' => 1])->assertForbidden();
        $scan->update(['expires_at' => now()->subMinute()]);
        $this->expectException(ValidationException::class);
        $scanner->apply($scan, $this->admin, [$test->id]);
    }

    public function test_a_batch_can_apply_all_the_preview_results_without_trusting_browser_ids(): void
    {
        $test = User::factory()->create(['name' => 'Prueba']);
        $scan = app(TestClientRestrictionScanner::class)->preview($this->admin);
        $this->actingAs($this->admin)->post(route('admin.client-restrictions.apply', $scan), ['apply_all' => 1, 'confirm' => 1])->assertRedirect();
        $this->assertTrue($test->hasIndividualClientBlock());
        $this->assertDatabaseCount('user_access_blocks', count($scan->candidates));
    }

    public function test_teamleader_and_monday_mirrors_hide_restricted_records_from_unauthorized_staff(): void
    {
        Schema::create('tl_contacts', function (Blueprint $table): void {
            $table->string('id')->primary();
        });
        foreach (['tl_projects', 'tl_deals', 'tl_invoices'] as $name) {
            Schema::create($name, function (Blueprint $table): void {
                $table->string('id')->primary(); $table->string('customer_type'); $table->string('customer_id');
            });
            DB::table($name)->insert(['id' => 'record', 'customer_type' => 'contact', 'customer_id' => 'contact']);
        }
        Schema::create('tl_documents', function (Blueprint $table): void {
            $table->string('id')->primary(); $table->string('entity_type'); $table->string('entity_id');
        });
        Schema::create('monday_items', function (Blueprint $table): void {
            $table->string('id')->primary(); $table->softDeletes();
        });
        DB::table('tl_contacts')->insert(['id' => 'contact']);
        DB::table('tl_documents')->insert(['id' => 'document', 'entity_type' => 'contact', 'entity_id' => 'contact']);
        DB::table('monday_items')->insert(['id' => 'item']);
        $this->client->update(['tl_id' => 'contact', 'monday_id' => 'item']);
        $this->block();
        $staff = User::factory()->create()->syncRoles(['Ventas']);
        foreach ([\App\Models\TlContact::class, \App\Models\TlDeal::class, \App\Models\TlProject::class,
            \App\Models\TlInvoice::class, \App\Models\TlDocument::class, \App\Models\MondayItem::class] as $model) {
            $this->actingAs($staff); $this->assertSame(0, $model::count(), $model);
            $this->actingAs($this->admin); $this->assertSame(1, $model::count(), $model);
        }
    }

    public function test_a_hold_discovered_during_a_request_withholds_the_response(): void
    {
        $client = $this->client;
        $admin = $this->admin;
        Route::middleware(['web', 'auth'])->get('/restriction-refresh/{user}', function (User $user) use ($admin) {
            app(ClientRestrictionService::class)->block($user, 'retired', actor: $admin);
            return response()->json(['secret' => 'Confidential data']);
        });
        $staff = User::factory()->create()->syncRoles(['Ventas']);
        $this->actingAs($staff)->getJson('/restriction-refresh/'.$client->id)->assertForbidden()->assertDontSee('Confidential data');
    }

    public function test_application_document_links_stream_content_without_s3_redirects_and_recheck_revoked_access(): void
    {
        Storage::fake('s3');
        $path = 'clients/P123/document.pdf';
        Storage::disk('s3')->put($path, '%PDF-1.4 confidential document');
        $file = File::create(['user_id' => $this->client->id, 'IDCliente' => 'P123', 'location' => 'clients/P123', 'file' => 'document.pdf']);
        $url = route('client-files.content', $file);
        $this->get($url)->assertRedirect(route('login'));
        $response = $this->actingAs($this->client)->get($url)->assertOk();
        $this->assertSame('%PDF-1.4 confidential document', $response->streamedContent());
        $this->assertNull($response->headers->get('Location'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('inline', $response->headers->get('Content-Disposition'));
        $other = User::factory()->create();
        $this->actingAs($other)->getJson($url)->assertForbidden();
        $this->block();
        $this->actingAs($this->client)->get($url)->assertRedirect(route('client-access-blocked'));
        $this->actingAs($this->admin)->getJson($url)->assertStatus(423);
        $this->assertSame('%PDF-1.4 confidential document', Storage::disk('s3')->get($path));
    }

    public function test_file_resolution_returns_an_application_link_and_never_requests_a_signed_s3_url(): void
    {
        $file = File::create(['user_id' => $this->client->id, 'IDCliente' => 'P123', 'location' => 'clients/P123', 'file' => 'document.pdf']);
        Gate::define('tree-files.view', fn () => true);
        $this->actingAs($this->admin)->postJson(route('openfile'), ['path' => 'clients/P123/document.pdf'])
            ->assertOk()->assertJsonPath('url', route('client-files.content', $file));
    }

    public function test_file_serving_only_reads_storage_and_forces_html_downloads(): void
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, '<script>malicious()</script>'); rewind($stream);
        $disk = \Mockery::mock();
        $disk->shouldReceive('readStream')->once()->with('document.html')->andReturn($stream);
        $disk->shouldReceive('mimeType')->once()->with('document.html')->andReturn('text/html');
        Storage::shouldReceive('disk')->once()->with('s3')->andReturn($disk);
        $response = app(\App\Services\ApplicationFileResponse::class)->stream('s3', 'document.html', 'documento 100%.html');
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('sandbox', $response->headers->get('Content-Security-Policy'));
        ob_start(); $response->sendContent(); $content = ob_get_clean();
        $this->assertSame('<script>malicious()</script>', $content);
    }

    public function test_requested_documents_require_ownership_and_suspend_delivery_after_a_hold(): void
    {
        $this->admin->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate('crud.users.index', 'web'));
        Schema::create('document_requests', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('user_id'); $table->string('file_path');
        });
        $id = DB::table('document_requests')->insertGetId(['user_id' => $this->client->id, 'file_path' => 'requests/document.pdf']);
        Storage::fake('s3');
        Storage::disk('s3')->put('requests/document.pdf', '%PDF-1.4 requested');
        $url = route('client-document-requests.content', $id);
        $this->actingAs(User::factory()->create())->getJson($url)->assertForbidden();
        $response = $this->actingAs($this->client)->get($url)->assertOk();
        $this->assertSame('%PDF-1.4 requested', $response->streamedContent());
        $this->assertNull($response->headers->get('Location'));
        $this->block();
        $this->actingAs($this->admin)->getJson($url)->assertStatus(423);
    }

    #[DataProvider('teamleaderStoredDocumentTypes')]
    public function test_teamleader_document_links_use_the_app_and_reject_delivery_of_held_records(string $type): void
    {
        $this->admin->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate('tl.view', 'web'));
        [$table, $model] = match ($type) {
            'invoice' => ['tl_invoices', \App\Models\TlInvoice::class],
            'credit-note' => ['tl_credit_notes', \App\Models\TlCreditNote::class],
            'document' => ['tl_documents', \App\Models\TlDocument::class],
        };
        Schema::create($table, function (Blueprint $table) use ($type): void {
            $table->string('id')->primary();
            if ($type === 'document') {
                $table->string('name'); $table->string('entity_type'); $table->string('entity_id');
                $table->string('s3_path'); $table->string('s3_disk'); $table->boolean('downloaded');
            } else {
                $table->string('customer_type'); $table->string('customer_id');
                $table->string('pdf_s3_path'); $table->string('pdf_s3_disk');
            }
        });
        DB::table($table)->insert($type === 'document'
            ? ['id' => 'record', 'name' => 'document.pdf', 'entity_type' => 'contact', 'entity_id' => 'contact', 's3_path' => 'tl/document.pdf', 's3_disk' => 's3', 'downloaded' => true]
            : ['id' => 'record', 'customer_type' => 'contact', 'customer_id' => 'contact', 'pdf_s3_path' => 'tl/document.pdf', 'pdf_s3_disk' => 's3']);
        $this->client->update(['tl_id' => 'contact']);
        Storage::fake('s3'); Storage::disk('s3')->put('tl/document.pdf', '%PDF-1.4 teamleader');
        $record = $model::findOrFail('record');
        $url = $type === 'document' ? $record->temporary_url : $record->pdf_url;
        $this->assertSame(parse_url(config('app.url'), PHP_URL_HOST), parse_url($url, PHP_URL_HOST));
        $this->get($url)->assertRedirect(route('login'));
        $response = $this->actingAs($this->admin)->get($url)->assertOk();
        $this->assertSame('%PDF-1.4 teamleader', $response->streamedContent());
        $this->assertNull($response->headers->get('Location'));
        $this->block();
        $this->getJson($url)->assertStatus(423);
        $staff = User::factory()->create()->syncRoles(['Ventas']);
        Gate::define('tl.view', fn () => true);
        $this->actingAs($staff)->getJson($url)->assertNotFound();
    }

    public static function teamleaderStoredDocumentTypes(): array
    {
        return [['invoice'], ['credit-note'], ['document']];
    }

    public function test_missing_stored_documents_return_not_found_without_redirecting(): void
    {
        Storage::fake('s3');
        $file = File::create(['user_id' => $this->client->id, 'IDCliente' => 'P123', 'location' => 'clients/P123', 'file' => 'missing.pdf']);
        $response = $this->actingAs($this->client)->getJson(route('client-files.content', $file))->assertNotFound();
        $this->assertNull($response->headers->get('Location'));
    }

    private function block(): void
    {
        app(ClientRestrictionService::class)->block($this->client, 'retired', actor: $this->admin);
    }
}
