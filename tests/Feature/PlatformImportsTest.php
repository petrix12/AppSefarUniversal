<?php
namespace Tests\Feature;

use App\Http\Middleware\AuditRequests;
use App\Models\{PlatformRecord,User};
use App\Services\PlatformMirror;
use Illuminate\Support\Facades\{DB,Http,Mail,Schema};
use Illuminate\Support\Str;
use Spatie\Permission\Models\{Permission,Role};
use Tests\TestCase;

class PlatformImportsTest extends TestCase
{
    use \Tests\Concerns\RegistrationSchema;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default'=>'array','session.driver'=>'array','services.stripe.secret'=>'sk_test_fake',
            'services.stripe.history_es_secret'=>null,'kalendia.token'=>'fake','services.paypal.history_es_secret'=>null,
            'services.paypal.history_es_client_id'=>null,'app.key'=>'base64:'.base64_encode(str_repeat('s',32))]);
        $this->withoutMiddleware(AuditRequests::class); Http::preventStrayRequests(); Mail::fake();
        $this->createRegistrationSchema();
        Schema::table('users',fn($t)=>$t->boolean('is_superadmin')->default(false));
        $this->artisan('migrate',['--path'=>[
            'database/migrations/2026_11_20_180000_create_kalendia_bookings_table.php',
            'database/migrations/2026_11_20_190000_create_kalendia_consultation_management_tables.php',
            'database/migrations/2026_11_22_120000_create_kalendia_webhook_events_table.php',
            'database/migrations/2026_11_22_180000_create_platform_management_tables.php',
        ]])->assertExitCode(0);
        Role::findOrCreate('Administrador');
    }
    private function admin(): User
    {
        $user=User::factory()->create();$user->syncRoles(['Administrador']);return $user;
    }
    private function row(string $id): array { return ['id'=>$id,'livemode'=>false]; }

    public function test_global_dashboard_and_request_queue_are_local_and_idempotent(): void
    {
        $this->actingAs($this->admin());
        $this->get(route('admin.platforms.imports.index'))->assertOk()->assertSee('Avance por recurso')->assertSee('PayPal');
        $this->post(route('admin.platforms.imports.store'))->assertRedirect(route('admin.platforms.imports.index'));
        $this->assertSame(22,DB::table('platform_sync_states')->where('requested',true)->count());
        DB::table('platform_sync_states')->where('resource','charges')->update(['cursor'=>'ch_resume','has_more'=>true,'page'=>4]);
        $this->post(route('admin.platforms.imports.store'))->assertRedirect();
        $this->assertDatabaseCount('platform_sync_states',22);
        $this->assertSame('ch_resume',DB::table('platform_sync_states')->where('resource','charges')->value('cursor'));
        $this->assertDatabaseCount('compras',0);$this->assertDatabaseCount('facturas',0);Http::assertNothingSent();
    }
    public function test_clients_cannot_import_even_with_the_platform_permission_and_invalid_pairs_never_queue(): void
    {
        $this->get(route('admin.platforms.imports.index'))->assertRedirect(route('login'));
        $client=User::factory()->create(['is_superadmin'=>true]);$client->syncRoles(['Cliente']);
        Permission::findOrCreate('platforms.manage');$client->givePermissionTo('platforms.manage');
        $this->actingAs($client)->postJson(route('admin.platforms.imports.store'))->assertForbidden();
        $this->get(route('admin.platforms.imports.index'))->assertForbidden();
        $this->actingAs($this->admin())->postJson(route('admin.platforms.imports.store'),['provider'=>'kalendia','account'=>'spain_legacy'])->assertUnprocessable();
        $this->assertDatabaseCount('platform_sync_states',0);Http::assertNothingSent();
    }
    public function test_missing_migration_renders_pending_without_importing_or_contacting_providers(): void
    {
        Schema::drop('platform_sync_states');$this->actingAs($this->admin());
        $this->get(route('admin.platforms.imports.index'))->assertOk()->assertSee('Falta aplicar la migración');
        $this->post(route('admin.platforms.imports.store'))->assertSessionHasErrors('imports');Http::assertNothingSent();
    }
    public function test_subscription_backfill_includes_canceled_subscriptions_without_creating_purchases(): void
    {
        $mirror=app(PlatformMirror::class);$mirror->queue('stripe','main','subscriptions');
        Http::fake(['api.stripe.com/*'=>Http::response(['data'=>[$this->row('sub_cancelled')+['status'=>'canceled']],'has_more'=>false])]);
        $this->assertSame(1,$mirror->sync(1)['records']);
        Http::assertSent(fn($r)=>$r->method()==='GET' && $r['status']==='all' && (int)$r['limit']===100);
        $this->assertSame('canceled',PlatformRecord::sole()->status);
        $this->assertDatabaseCount('compras',0);$this->assertDatabaseCount('facturas',0);
    }
    public function test_archived_prices_are_imported_in_a_separate_resumable_pass(): void
    {
        $mirror=app(PlatformMirror::class);$mirror->queue('stripe','main','prices');$fail=true;
        Http::fake(function($r)use(&$fail){
            $active=filter_var($r['active'],FILTER_VALIDATE_BOOLEAN);
            if($active)return Http::response(['data'=>[$this->row('price_active')+['active'=>true]],'has_more'=>false]);
            if($fail)return Http::response([],503);
            return Http::response(['data'=>[$this->row('price_archived')+['active'=>false]],'has_more'=>false]);
        });
        $this->assertSame(1,$mirror->sync(1)['records']);
        $this->assertSame('inactive:',DB::table('platform_sync_states')->value('cursor'));
        $this->assertSame(1,$mirror->sync(1)['errors']);
        $fail=false;$this->assertTrue($mirror->queue('stripe','main','prices'));
        $this->assertSame('inactive:',DB::table('platform_sync_states')->value('cursor'));
        $this->assertSame(1,$mirror->sync(1)['records']);
        $this->assertDatabaseCount('platform_records',2);
        $this->assertFalse((bool)DB::table('platform_sync_states')->value('requested'));
        $this->assertSame(1,Http::recorded()->filter(fn($pair)=>filter_var($pair[0]['active'],FILTER_VALIDATE_BOOLEAN))->count());
    }
    public function test_short_kalendia_pages_without_totals_continue_until_empty(): void
    {
        $mirror=app(PlatformMirror::class);$mirror->queue('kalendia','main','customers');
        Http::fake(function($r){$items=match((int)$r['page']){1=>[['id'=>'cli_one'],['id'=>'cli_two']],2=>[['id'=>'cli_three']],default=>[]};return Http::response(['data'=>['items'=>$items]]);});
        $this->assertSame(['records'=>3,'pages'=>3,'errors'=>0],$mirror->sync(3));
        $this->assertDatabaseCount('platform_records',3);Http::assertSentCount(3);
        $this->assertFalse((bool)DB::table('platform_sync_states')->value('requested'));
    }
    public function test_a_provider_ignoring_pagination_stops_for_review_without_looping_or_duplicate_records(): void
    {
        $mirror=app(PlatformMirror::class);$mirror->queue('kalendia','main','customers');
        Http::fake(['*'=>Http::response(['data'=>['items'=>[['id'=>'cli_one']]]])]);
        $result=$mirror->sync(10);
        $this->assertSame(1,$result['records']);$this->assertSame(1,$result['errors']);
        $this->assertDatabaseCount('platform_records',1);Http::assertSentCount(2);
        $this->assertSame('pagination_stalled',DB::table('platform_sync_states')->value('error_code'));
    }
    public function test_a_large_collection_does_not_starve_other_requested_resources(): void
    {
        $mirror=app(PlatformMirror::class);$mirror->queue('stripe','main','customers');$mirror->queue('stripe','main','charges');
        Http::fake(fn($r)=>Http::response(['data'=>[$this->row(str_contains($r->url(),'customers')?'cus_one':'ch_one')],'has_more'=>str_contains($r->url(),'customers')]));
        $this->assertSame(2,$mirror->sync(2)['records']);
        $this->assertDatabaseHas('platform_records',['remote_id'=>'ch_one']);Http::assertSentCount(2);
    }
    public function test_legacy_page_sizes_and_subscription_filters_restart_safely_without_skipping_records(): void
    {
        $mirror=app(PlatformMirror::class);$mirror->queue('kalendia','main','customers');
        DB::table('platform_sync_states')->update(['page'=>3,'cursor'=>'cli_old_page','has_more'=>true]);
        Http::fake(fn($r)=>Http::response(str_contains($r->url(),'subscriptions')
            ? ['data'=>[$this->row('sub_newest_canceled')+['status'=>'canceled']],'has_more'=>false]
            : ['data'=>['items'=>[['id'=>'cli_newest']],'pagination'=>['pages'=>1]]]));
        $this->assertSame(1,$mirror->sync(1)['records']);
        Http::assertSent(fn($r)=>(int)$r['page']===1 && (int)$r['per_page']===100);
        $mirror->queue('stripe','main','subscriptions');
        DB::table('platform_sync_states')->where('provider','stripe')->update(['page'=>3,'cursor'=>'sub_old_active','has_more'=>true]);
        $this->assertSame(1,$mirror->sync(1)['records']);
        Http::assertSent(fn($r)=>str_contains($r->url(),'subscriptions') && $r['status']==='all' && !isset($r['starting_after']));
        $this->assertDatabaseHas('platform_records',['remote_id'=>'sub_newest_canceled']);
    }
    public function test_an_expired_worker_cannot_write_after_another_worker_takes_ownership(): void
    {
        $mirror=app(PlatformMirror::class);$mirror->queue('stripe','main','charges');$newOwner=(string)Str::uuid();
        Http::fake(function()use($newOwner){DB::table('platform_sync_states')->update(['lease'=>$newOwner,'leased_until'=>now()->addMinute()]);return Http::response(['data'=>[$this->row('ch_one')],'has_more'=>false]);});
        $this->assertSame(0,$mirror->sync(1)['records']);
        $this->assertDatabaseCount('platform_records',0);
        $this->assertSame($newOwner,DB::table('platform_sync_states')->value('lease'));
        $this->assertTrue((bool)DB::table('platform_sync_states')->value('requested'));
    }
}
