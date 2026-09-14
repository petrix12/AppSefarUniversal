<?php

namespace Tests\Feature;

use App\Models\Agcliente;
use App\Models\DocumentRequest;
use App\Models\File;
use App\Models\GenealogyUnion;
use App\Models\User;
use App\Mail\GenealogyDocumentUploaded;
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

        $response = $this->actingAs($client)
            ->getJson(route('clientes.tree.person-detail', $root));

        $response->assertOk()
            ->assertJsonPath('allowed_document_kinds.passport', 'Pasaporte')
            ->assertJsonPath('allowed_document_kinds.birth_certificate', 'Acta de nacimiento')
            ->assertJsonPath('allowed_document_kinds.marriage_certificate', 'Acta de matrimonio')
            ->assertJsonMissingPath('allowed_document_kinds.death_certificate')
            ->assertJsonPath('document_requests.0.document_kind', 'passport')
            ->assertJsonPath('document_requests.0.status', 'en_espera_cliente')
            ->assertJsonPath('reusable_files.0.id', $uploadedFile->id);
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
        $client = User::factory()->create(['passport' => 'V22222222']);
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
}
