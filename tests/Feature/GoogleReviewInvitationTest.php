<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\GoogleReviewInvitationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GoogleReviewInvitationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('agclientes', function (Blueprint $table) {
            $table->id();
            $table->string('IDCliente');
            $table->timestamps();
        });

        $clientRole = Role::findOrCreate('Cliente');
        Permission::findOrCreate('cliente')->syncRoles($clientRole);

        config()->set('reviews.google_write_review_url', 'https://search.google.com/local/writereview?placeid=test');
    }

    public function test_only_recent_clients_with_five_people_and_a_pending_campaign_are_eligible(): void
    {
        $service = app(GoogleReviewInvitationService::class);

        $eligible = $this->createClient(now()->subDays(29), 5);
        $this->assertTrue($service->canInvite($eligible));

        $this->assertFalse($service->canInvite($this->createClient(now()->subDays(29), 4)));
        $this->assertFalse($service->canInvite($this->createClient(now()->subMonth()->subSecond(), 5)));

        $eligible->forceFill(['google_review_completed_at' => now()])->save();
        $this->assertFalse($service->canInvite($eligible->fresh()));
    }

    public function test_clicking_the_invitation_completes_it_and_redirects_only_to_the_direct_write_url(): void
    {
        $client = $this->createClient(now()->subDays(10), 5);

        $response = $this->actingAs($client)->post(route('clientes.google-review'));

        $response->assertRedirect('https://search.google.com/local/writereview?placeid=test');
        $this->assertNotNull($client->fresh()->google_review_completed_at);
    }

    public function test_the_modal_only_contains_the_internal_review_route(): void
    {
        $html = view('components.google-review-invitation', [
            'googleReviewInvitation' => true,
        ])->render();

        $this->assertStringContainsString(route('clientes.google-review'), $html);
        $this->assertStringNotContainsString('search.google.com', $html);
        $this->assertStringContainsString('no verás las reseñas de otros clientes', $html);
    }

    private function createClient($createdAt, int $people): User
    {
        $passport = 'P' . User::query()->count() . random_int(1000, 9999);
        $client = User::factory()->create([
            'passport' => $passport,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        foreach (range(1, $people) as $person) {
            DB::table('agclientes')->insert([
                'IDCliente' => $passport,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $client->fresh();
    }
}
