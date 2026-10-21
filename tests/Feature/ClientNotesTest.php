<?php

namespace Tests\Feature;

use App\Models\ClientNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClientNotesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('Cliente', 'web');
        Role::findOrCreate('Administrador', 'web');
        Permission::findOrCreate('crud.users.index', 'web');
        Http::preventStrayRequests();
        Notification::fake();
    }

    public function test_api_requires_a_valid_bearer_token(): void
    {
        $client = User::factory()->create();
        $this->postJson($this->apiUrl($client), ['note' => 'Test'])->assertUnauthorized();
        $this->withToken('invalid')->postJson($this->apiUrl($client), ['note' => 'Test'])->assertUnauthorized();
        $this->assertDatabaseCount('client_notes', 0);
    }

    public function test_a_web_session_does_not_replace_the_api_token(): void
    {
        $client = User::factory()->create();
        $this->actingAs($this->editor());

        $this->postJson($this->apiUrl($client), ['note' => 'Test'])->assertUnauthorized();
        $this->withToken('invalid')->postJson($this->apiUrl($client), ['note' => 'Test'])->assertUnauthorized();
        $this->assertDatabaseCount('client_notes', 0);
    }

    #[DataProvider('unrelatedAbilities')]
    public function test_api_requires_the_specific_note_ability(string $ability): void
    {
        $client = User::factory()->create();
        $token = $this->editor()->createToken('Other permission', [$ability])->plainTextToken;

        $this->withToken($token)->postJson($this->apiUrl($client), ['note' => 'Test'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Se requiere un token de usuario interno con permiso client-notes:create.');
        $this->assertDatabaseCount('client_notes', 0);
    }

    public static function unrelatedAbilities(): array
    {
        return [['read'], ['create'], ['cos:refresh'], ['mcp:read']];
    }

    public function test_client_tokens_cannot_create_notes_even_with_the_ability(): void
    {
        $client = User::factory()->create();
        $token = $client->createToken('Client', ['client-notes:create'])->plainTextToken;

        $this->withToken($token)->postJson($this->apiUrl($client), ['note' => 'Test'])->assertForbidden();
        $this->assertDatabaseCount('client_notes', 0);
    }

    public function test_revoked_and_expired_tokens_cannot_create_notes(): void
    {
        $client = User::factory()->create();
        $editor = $this->editor();
        $revoked = $editor->createToken('Revoked', ['client-notes:create']);
        $revoked->accessToken->delete();
        $expired = $editor->createToken('Expired', ['client-notes:create'], now()->subMinute());

        $this->withToken($revoked->plainTextToken)->postJson($this->apiUrl($client), ['note' => 'Test'])->assertUnauthorized();
        $this->withToken($expired->plainTextToken)->postJson($this->apiUrl($client), ['note' => 'Test'])->assertUnauthorized();
        $this->assertDatabaseCount('client_notes', 0);
    }

    public function test_api_only_accepts_an_existing_client_as_the_target(): void
    {
        $editor = $this->editor();
        $token = $editor->createToken('Notes', ['client-notes:create'])->plainTextToken;

        $this->withToken($token)->postJson($this->apiUrl($editor), ['note' => 'Test'])->assertNotFound();
        $this->withToken($token)->postJson('/api/v1/clientes/999999/notas', ['note' => 'Test'])->assertNotFound();
        $this->assertDatabaseCount('client_notes', 0);
    }

    public function test_api_saves_a_separate_note_for_the_target_client_with_the_token_owner_as_author(): void
    {
        $client = User::factory()->create(['arraycos' => [['status' => 'unchanged']]]);
        $otherClient = User::factory()->create();
        $editor = $this->editor();
        $previousClient = $client->fresh()->getRawOriginal();
        $token = $editor->createToken('Notes', ['client-notes:create'])->plainTextToken;

        $response = $this->withToken($token)->postJson($this->apiUrl($client), [
            'note' => "  Seguimiento desde n8n.\nSe revisó el expediente.  ",
            'client_id' => $otherClient->id,
            'user_id' => $otherClient->id,
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'Nota creada correctamente.')
            ->assertJsonPath('data.client_id', $client->id)
            ->assertJsonPath('data.note', "Seguimiento desde n8n.\nSe revisó el expediente.")
            ->assertJsonPath('data.author.id', $editor->id);

        $note = ClientNote::query()->sole();
        $this->assertSame($response->json('data.id'), $note->id);
        $this->assertSame($client->id, $note->client->id);
        $this->assertSame($editor->id, $note->author->id);
        $this->assertSame($previousClient, $client->fresh()->getRawOriginal());
        $this->assertSame(0, $otherClient->notes()->count());
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertDatabaseCount('client_chat_messages', 0);
        Notification::assertNothingSent();
        Http::assertNothingSent();
    }

    #[DataProvider('invalidNotes')]
    public function test_api_rejects_invalid_notes(mixed $note): void
    {
        $client = User::factory()->create();
        $token = $this->editor()->createToken('Notes', ['client-notes:create'])->plainTextToken;

        $this->withToken($token)->postJson($this->apiUrl($client), ['note' => $note])
            ->assertUnprocessable()->assertJsonValidationErrors('note');
        $this->assertDatabaseCount('client_notes', 0);
    }

    public static function invalidNotes(): array
    {
        return [
            'null' => [null], 'empty' => [''], 'whitespace' => [" \n\t "],
            'array' => [['invalid']], 'number' => [123], 'too long' => [str_repeat('x', ClientNote::MAX_LENGTH + 1)],
        ];
    }

    public function test_api_accepts_a_note_at_the_character_limit(): void
    {
        $client = User::factory()->create();
        $token = $this->editor()->createToken('Notes', ['client-notes:create'])->plainTextToken;
        $note = str_repeat('á', ClientNote::MAX_LENGTH);

        $this->withToken($token)->postJson($this->apiUrl($client), ['note' => $note])
            ->assertCreated()->assertJsonPath('data.note', $note);
        $this->assertSame($note, ClientNote::query()->sole()->note);
    }

    public function test_web_module_requires_permission_and_rejects_clients(): void
    {
        $client = User::factory()->create();
        $this->get($this->webUrl($client))->assertRedirect(route('login'));
        $this->actingAs($this->editor(false))->get($this->webUrl($client))->assertForbidden();
        $client->givePermissionTo('crud.users.index');
        $this->actingAs($client)->get($this->webUrl($client))->assertForbidden();
        $this->actingAs($client)->post($this->webUrl($client), ['note' => 'Private note'])->assertForbidden();
        $this->assertDatabaseCount('client_notes', 0);
    }

    public function test_web_module_creates_notes_and_redirects_to_the_client_notes_page(): void
    {
        $client = User::factory()->create();
        $editor = $this->editor();

        $this->actingAs($editor)->post($this->webUrl($client), ['note' => 'Nota desde la ficha.'])
            ->assertRedirect($this->webUrl($client))
            ->assertSessionHas('success', 'Nota creada correctamente.');

        $note = $client->notes()->sole();
        $this->assertSame($editor->id, $note->user_id);
        $this->assertSame('Nota desde la ficha.', $note->note);
        Notification::assertNothingSent();
    }

    public function test_web_module_lists_only_the_client_notes_and_escapes_the_content(): void
    {
        $client = User::factory()->create();
        $otherClient = User::factory()->create();
        $editor = $this->editor();
        $client->notes()->create(['user_id' => $editor->id, 'note' => '<script>private_note_payload()</script>']);
        $otherClient->notes()->create(['user_id' => $editor->id, 'note' => 'OTHER_CLIENT_PRIVATE_NOTE']);

        $this->actingAs($editor)->get($this->webUrl($client))
            ->assertOk()
            ->assertViewIs('crud.users.notes.index')
            ->assertSee('<script>private_note_payload()</script>')
            ->assertDontSee('<script>private_note_payload()</script>', false)
            ->assertDontSee('OTHER_CLIENT_PRIVATE_NOTE')
            ->assertSee(route('crud.users.notes.store', $client), false);
    }

    public function test_web_module_paginates_notes_with_newest_first_and_keeps_notes_when_the_author_is_deleted(): void
    {
        $client = User::factory()->create();
        $author = $this->editor();
        for ($i = 1; $i <= 21; $i++) {
            $client->notes()->create(['user_id' => $author->id, 'note' => 'NOTE_' . $i]);
        }
        $author->delete();

        $this->actingAs($this->editor())->get($this->webUrl($client))
            ->assertOk()
            ->assertViewHas('notes', fn ($notes) => $notes->total() === 21
                && $notes->count() === 20 && $notes->first()->note === 'NOTE_21')
            ->assertSee('Usuario eliminado');
        $this->get($this->webUrl($client) . '?page=2')
            ->assertOk()->assertViewHas('notes', fn ($notes) => $notes->count() === 1 && $notes->first()->note === 'NOTE_1');
        $this->assertDatabaseCount('client_notes', 21);
        $this->assertNull($client->notes()->first()->user_id);
    }

    private function editor(bool $withPermission = true): User
    {
        $user = User::factory()->create();
        $user->syncRoles(['Administrador']);
        if ($withPermission) {
            $user->givePermissionTo('crud.users.index');
        }

        return $user;
    }

    private function apiUrl(User $client): string
    {
        return '/api/v1/clientes/' . $client->id . '/notas';
    }

    private function webUrl(User $client): string
    {
        return route('crud.users.notes.index', $client);
    }
}
