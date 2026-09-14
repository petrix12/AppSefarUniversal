<?php

namespace Tests\Feature;

use App\Models\Agcliente;
use App\Models\DocumentRequest;
use App\Models\File;
use App\Models\GenealogyUnion;
use App\Models\Negocio;
use App\Models\User;
use App\Mail\GenealogyDocumentUploaded;
use App\Services\CosService;
use App\Services\GenealogyDocumentService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TreeDocumentChecklistTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $clientRole = Role::findOrCreate('Cliente');
        Permission::findOrCreate('cliente')->syncRoles($clientRole);

        // The production agclientes table predates the migration history. Keep
        // this small test schema local to the feature test.
        if (! Schema::hasTable('agclientes')) {
            Schema::create('agclientes', function (Blueprint $table) {
                $table->id();
                $table->string('IDCliente');
                $table->string('IDPersona');
                $table->string('Nombres')->nullable();
                $table->string('Apellidos')->nullable();
                $table->timestamps();
            });
        }
    }

    public function test_client_tree_detail_lists_required_documents_requests_and_own_unclassified_uploads(): void
    {
        $client = User::factory()->create(['passport' => 'V12345678']);
        $client->assignRole('Cliente');
        $root = Agcliente::create([
            'IDCliente' => $client->passport,
            'IDPersona' => '1',
            'Nombres' => 'Cliente',
            'Apellidos' => 'Prueba',
        ]);

        DocumentRequest::create([
            'user_id' => $client->id,
            'requested_by' => $client->id,
            'document_name' => 'Pasaporte',
            'document_type' => 'genealogico',
            'document_kind' => 'passport',
            'person_id' => $root->id,
            'status' => 'en_espera_cliente',
        ]);
        $uploadedFile = File::create([
            'file' => 'archivo-previamente-cargado.pdf',
            'location' => 'public/doc/P' . $client->passport,
            'IDCliente' => $client->passport,
            'IDPersona' => 0,
            'user_id' => $client->id,
            'source' => 'app_cliente',
            'client_visible' => false,
        ]);
        $hubspotPassport = File::create([
            'file' => 'pasaporte-hubspot.pdf',
            'location' => 'public/doc/P' . $client->passport,
            'IDCliente' => $client->passport,
            'IDPersona' => 0,
            'IDPersonaNew' => $root->id,
            'user_id' => $client->id,
            'source' => 'hubspot',
            'source_reference' => 'hubspot:pasaporte__documento_:example',
            'document_kind' => 'passport',
            'client_visible' => false,
        ]);

        $response = $this->actingAs($client)
            ->getJson(route('clientes.tree.person-detail', $root));

        $response->assertOk()
            ->assertJsonPath('allowed_document_kinds.passport', 'Pasaporte')
            ->assertJsonPath('allowed_document_kinds.birth_certificate', 'Acta de nacimiento')
            ->assertJsonPath('allowed_document_kinds.marriage_certificate', 'Acta de matrimonio')
            ->assertJsonMissingPath('allowed_document_kinds.death_certificate')
            ->assertJsonPath('document_requests.0.document_kind', 'passport')
            ->assertJsonPath('document_requests.0.status', 'en_espera_cliente')
            ->assertJsonFragment(['id' => $uploadedFile->id])
            ->assertJsonFragment(['file' => $hubspotPassport->file]);
    }

    public function test_client_tree_detail_adds_death_certificate_for_an_ancestor(): void
    {
        $client = User::factory()->create(['passport' => 'V87654321']);
        $client->assignRole('Cliente');
        $ancestor = Agcliente::create([
            'IDCliente' => $client->passport,
            'IDPersona' => '2',
            'Nombres' => 'Familiar',
            'Apellidos' => 'Prueba',
        ]);

        $this->actingAs($client)
            ->getJson(route('clientes.tree.person-detail', $ancestor))
            ->assertOk()
            ->assertJsonPath('allowed_document_kinds.death_certificate', 'Acta de defunción');
    }

    public function test_client_can_associate_an_unclassified_app_upload_to_a_requested_document(): void
    {
        $client = User::factory()->create([
            'passport' => 'V22222222',
            'arraycos_expire' => now()->addDay(),
        ]);
        $client->assignRole('Cliente');
        $person = Agcliente::create([
            'IDCliente' => $client->passport,
            'IDPersona' => '1',
            'Nombres' => 'Cliente',
            'Apellidos' => 'Asociación',
        ]);
        $documentRequest = DocumentRequest::create([
            'user_id' => $client->id,
            'requested_by' => $client->id,
            'document_name' => 'Pasaporte',
            'document_type' => 'genealogico',
            'document_kind' => 'passport',
            'person_id' => $person->id,
            'status' => 'en_espera_cliente',
        ]);
        $uploadedFile = File::create([
            'file' => 'pasaporte-cargado-antes.pdf',
            'location' => 'public/doc/P' . $client->passport,
            'IDCliente' => $client->passport,
            'IDPersona' => 0,
            'user_id' => $client->id,
            'source' => 'app_cliente',
            'client_visible' => false,
        ]);

        $this->actingAs($client)
            ->postJson(route('associate_existing', $documentRequest), ['file_id' => $uploadedFile->id])
            ->assertOk()
            ->assertJsonPath('request.status', 'resuelto')
            ->assertJsonPath('file.document_kind', 'passport');

        $this->assertDatabaseHas('files', [
            'id' => $uploadedFile->id,
            'document_kind' => 'passport',
            'document_request_id' => $documentRequest->id,
        ]);
        $this->assertDatabaseHas('genealogy_document_person', [
            'file_id' => $uploadedFile->id,
            'person_id' => $person->id,
        ]);
        $this->assertNull($client->fresh()->arraycos_expire);
    }

    public function test_uploaded_files_hub_only_lists_client_safe_documents_and_allows_private_app_previews(): void
    {
        $client = User::factory()->create(['passport' => 'V22223333']);
        $client->assignRole('Cliente');

        $privateAppUpload = File::create([
            'file' => 'pasaporte-cargado-en-app.pdf',
            'location' => 'public/doc/P' . $client->passport,
            'IDCliente' => $client->passport,
            'IDPersona' => 0,
            'user_id' => $client->id,
            'source' => 'app_cliente',
            'client_visible' => false,
        ]);
        $safeHubspotFile = File::create([
            'file' => 'pasaporte-importado-seguro.pdf',
            'location' => 'public/doc/P' . $client->passport,
            'IDCliente' => $client->passport,
            'IDPersona' => 0,
            'user_id' => $client->id,
            'source' => 'hubspot',
            'source_reference' => 'hubspot:pasaporte__documento_:example',
            'document_kind' => 'passport',
            'client_visible' => false,
        ]);
        $internalTeamleaderFile = File::create([
            'file' => 'nota-interna-teamleader.pdf',
            'location' => 'public/doc/P' . $client->passport,
            'IDCliente' => $client->passport,
            'IDPersona' => 0,
            'user_id' => $client->id,
            'source' => 'teamleader',
            'document_kind' => 'passport',
            'client_visible' => true,
        ]);
        $internalHubspotFile = File::create([
            'file' => 'archivo-interno-hubspot.pdf',
            'location' => 'public/doc/P' . $client->passport,
            'IDCliente' => $client->passport,
            'IDPersona' => 0,
            'user_id' => $client->id,
            'source' => 'hubspot',
            'source_reference' => 'hubspot:archivo_interno:example',
            'document_kind' => 'passport',
            'client_visible' => true,
        ]);

        $this->actingAs($client)
            ->get(route('clientes.uploaded-files'))
            ->assertOk()
            ->assertSee('Archivos subidos')
            ->assertSee('pasaporte-cargado-en-app.pdf')
            ->assertSee('pasaporte-importado-seguro.pdf')
            ->assertDontSee('nota-interna-teamleader.pdf')
            ->assertDontSee('archivo-interno-hubspot.pdf');

        $documents = app(GenealogyDocumentService::class);
        $this->assertTrue($documents->canView($client, $privateAppUpload));
        $this->assertTrue($documents->canView($client, $safeHubspotFile));
        $this->assertFalse($documents->canView($client, $internalTeamleaderFile));
        $this->assertFalse($documents->canView($client, $internalHubspotFile));
        $this->assertFalse($documents->canReuseForRequest($client, $internalTeamleaderFile));
        $this->assertFalse($documents->canReuseForRequest($client, $internalHubspotFile));
    }

    public function test_client_can_submit_an_available_document_without_an_internal_request(): void
    {
        $client = User::factory()->create(['passport' => 'V44444444']);
        $client->assignRole('Cliente');
        $person = Agcliente::create([
            'IDCliente' => $client->passport,
            'IDPersona' => '1',
            'Nombres' => 'Cliente',
            'Apellidos' => 'Carga directa',
        ]);
        $uploadedFile = File::create([
            'file' => 'documento-disponible.pdf',
            'location' => 'public/doc/P' . $client->passport,
            'IDCliente' => $client->passport,
            'IDPersona' => 0,
            'user_id' => $client->id,
            'source' => 'app_cliente',
            'client_visible' => false,
        ]);

        $this->actingAs($client)
            ->postJson(route('client.requests.self-submit'), [
                'person_id' => $person->id,
                'document_kind' => 'passport',
                'file_id' => $uploadedFile->id,
            ])
            ->assertOk()
            ->assertJsonPath('request.status', 'resuelto')
            ->assertJsonPath('request.document_kind', 'passport')
            ->assertJsonPath('file.id', $uploadedFile->id);

        $this->assertDatabaseHas('document_requests', [
            'user_id' => $client->id,
            'person_id' => $person->id,
            'document_kind' => 'passport',
            'status' => 'resuelto',
        ]);
        $this->assertDatabaseHas('genealogy_document_person', [
            'file_id' => $uploadedFile->id,
            'person_id' => $person->id,
        ]);
    }

    public function test_upload_notifies_the_configured_internal_recipient_after_storage_succeeds(): void
    {
        Storage::fake('s3');
        Mail::fake();
        config()->set('services.genealogy_documents.upload_notification_to', ['documentos@sefar.test']);

        $client = User::factory()->create(['passport' => 'V55555555']);
        $client->assignRole('Cliente');
        $person = Agcliente::create([
            'IDCliente' => $client->passport,
            'IDPersona' => '1',
            'Nombres' => 'Cliente',
            'Apellidos' => 'Notificación',
        ]);
        $documentRequest = DocumentRequest::create([
            'user_id' => $client->id,
            'requested_by' => $client->id,
            'document_name' => 'Pasaporte',
            'document_type' => 'genealogico',
            'document_kind' => 'passport',
            'person_id' => $person->id,
            'status' => 'en_espera_cliente',
        ]);

        $this->actingAs($client)
            ->post(route('upload', $documentRequest), [
                'file' => UploadedFile::fake()->create('pasaporte.pdf', 240, 'application/pdf'),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('request.status', 'resuelto');

        Mail::assertSent(GenealogyDocumentUploaded::class, function (GenealogyDocumentUploaded $mail) use ($client) {
            return $mail->hasTo('documentos@sefar.test')
                && $mail->user->is($client)
                && $mail->file->document_kind === 'passport';
        });

        $file = File::where('document_request_id', $documentRequest->id)->firstOrFail();
        $this->assertDatabaseHas('genealogy_document_person', [
            'file_id' => $file->id,
            'person_id' => $person->id,
        ]);
        $this->assertStringContainsString(
            'Nuevo archivo cargado',
            (new GenealogyDocumentUploaded($client, $file, $person))->render()
        );
    }

    public function test_marriage_request_is_visible_from_both_spouses_nodes(): void
    {
        $client = User::factory()->create(['passport' => 'V33333333']);
        $client->assignRole('Cliente');
        $firstSpouse = Agcliente::create([
            'IDCliente' => $client->passport,
            'IDPersona' => '1',
            'Nombres' => 'Primer',
            'Apellidos' => 'Cónyuge',
        ]);
        $secondSpouse = Agcliente::create([
            'IDCliente' => $client->passport,
            'IDPersona' => '2',
            'Nombres' => 'Segundo',
            'Apellidos' => 'Cónyuge',
        ]);
        $union = GenealogyUnion::create([
            'IDCliente' => $client->passport,
            'spouse_one_id' => $firstSpouse->id,
            'spouse_two_id' => $secondSpouse->id,
            'created_by' => $client->id,
        ]);
        DocumentRequest::create([
            'user_id' => $client->id,
            'requested_by' => $client->id,
            'document_name' => 'Acta de matrimonio',
            'document_type' => 'genealogico',
            'document_kind' => 'marriage_certificate',
            'person_id' => $firstSpouse->id,
            'genealogy_union_id' => $union->id,
            'status' => 'en_espera_cliente',
        ]);

        $this->actingAs($client)
            ->getJson(route('clientes.tree.person-detail', $secondSpouse))
            ->assertOk()
            ->assertJsonPath('document_requests.0.document_kind', 'marriage_certificate')
            ->assertJsonPath('document_requests.0.genealogy_union_id', $union->id);
    }

    public function test_document_stage_requires_approved_genealogy_but_not_a_phase_one_payment(): void
    {
        $client = User::factory()->create();
        $deal = new Negocio([
            'servicio_solicitado' => 'Española Sefardi',
            'servicio_solicitado2' => 'Española Sefardi',
        ]);
        DocumentRequest::create([
            'user_id' => $client->id,
            'requested_by' => $client->id,
            'document_name' => 'Pasaporte',
            'document_type' => 'genealogico',
            'document_kind' => 'passport',
            'status' => 'resuelto',
        ]);

        $withoutApproval = (new CosService($deal, $client, collect([$deal]), ['etiquetas' => 'En proceso'], false))
            ->calculateStatus();
        $withApproval = (new CosService($deal, $client, collect([$deal]), ['etiquetas' => 'Aceptado'], false))
            ->calculateStatus();

        $this->assertNotSame('Documentos en Revisión', $withoutApproval['description']);
        $this->assertSame('Documentos en Revisión', $withApproval['description']);
    }

    public function test_phase_one_exoneration_is_a_valid_cos_payment_state(): void
    {
        $client = User::factory()->create();
        $deal = new Negocio([
            'servicio_solicitado' => 'Española Sefardi',
            'servicio_solicitado2' => 'Española Sefardi',
            'fase_1_preestab' => 'EXONERADO 2026/09/14',
            'fase_1_pagado' => 'EXONERADO 2026/09/14',
        ]);

        $status = (new CosService($deal, $client, collect([$deal]), ['etiquetas' => 'Aceptado'], false))
            ->calculateStatus();

        $this->assertSame('Fase 1 Exonerada', $status['description']);
        $this->assertSame('Exonerada', $status['phasePayments'][0]['status']);
        $this->assertSame('Sin información', $status['phasePayments'][1]['status']);
    }

    public function test_document_person_relationship_labels_cover_client_parents_and_grandparents(): void
    {
        $makePerson = fn (string $id) => new Agcliente(['IDPersona' => $id]);

        $this->assertSame('Cliente', GenealogyDocumentService::relationshipLabel($makePerson('1')));
        $this->assertSame('Padre', GenealogyDocumentService::relationshipLabel($makePerson('2')));
        $this->assertSame('Madre', GenealogyDocumentService::relationshipLabel($makePerson('3')));
        $this->assertSame('Abuelo P', GenealogyDocumentService::relationshipLabel($makePerson('4')));
        $this->assertSame('Abuela M', GenealogyDocumentService::relationshipLabel($makePerson('7')));
    }
}
