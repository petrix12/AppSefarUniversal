<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Http, Mail, Route};
use JeroenNoten\LaravelAdminLte\Menu\Builder;
use JeroenNoten\LaravelAdminLte\Menu\Filters\GateFilter;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\{Permission, Role};
use Tests\TestCase;

class AdminNavigationTest extends TestCase
{
    use \Tests\Concerns\RegistrationSchema;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default'=>'array', 'session.driver'=>'array']);
        Http::preventStrayRequests(); Mail::fake();
        $this->createRegistrationSchema();
        Role::findOrCreate('Personal'); Role::findOrCreate('Administrador');
        Permission::findOrCreate('cliente'); Role::findByName('Cliente')->givePermissionTo('cliente');
    }

    private function actor(array $permissions=[], string $role='Personal'): User
    {
        $user=User::factory()->create(); $user->syncRoles([$role]);
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission); $user->givePermissionTo($permission);
        }
        return $user;
    }
    private function menu(User $user): array
    {
        $this->actingAs($user);
        $builder=new Builder([app(GateFilter::class)]);
        $builder->add(...config('adminlte.menu'));
        return $builder->menu();
    }
    private function leaves(array $menu): array
    {
        $result=[];
        foreach ($menu as $item) {
            if (isset($item['route']) || isset($item['url'])) $result[]=$item;
            if (isset($item['submenu'])) $result=array_merge($result,$this->leaves($item['submenu']));
        }
        return $result;
    }
    private function destinations(array $menu): array
    {
        return array_map(fn($item)=>$item['route']??$item['url'],$this->leaves($menu));
    }

    public function test_the_internal_navigation_has_eight_sections_and_no_duplicate_or_placeholder_destinations(): void
    {
        $menu=config('adminlte.menu');
        $this->assertSame(['Clientes y COS','Trabajo y ventas','Agenda y consultas','Finanzas','Servicios y difusión',
            'Genealogía y documentos','Integraciones','Configuración'],
            array_values(array_map(fn($i)=>$i['text'],array_filter($menu,fn($i)=>isset($i['key'])))));
        $destinations=$this->destinations($menu);
        $this->assertSame($destinations,array_values(array_unique($destinations)));
        foreach ($this->leaves($menu) as $item) {
            $this->assertNotContains($item['url']??null,['#','no_definido']);
            $this->assertNotSame('no_definido',$item['can']??null);
        }
        $depth=function(array $items,int $level=1)use(&$depth):void {
            $this->assertLessThanOrEqual(3,$level);
            foreach($items as $item)if(isset($item['submenu']))$depth($item['submenu'],$level+1);
        };
        $depth($menu);
    }

    public function test_every_configured_destination_has_a_real_get_route_and_uses_existing_theme_assets(): void
    {
        foreach ($this->leaves(config('adminlte.menu')) as $item) {
            $path=isset($item['route']) ? route($item['route'],[],false) : '/'.ltrim($item['url'],'/');
            $route=Route::getRoutes()->match(Request::create($path,'GET'));
            $this->assertContains('GET',$route->methods(),$item['text'].' → '.$path);
        }
        $this->assertSame('sidebar-dark-primary elevation-4 sefar-sidebar',config('adminlte.classes_sidebar'));
        $this->assertTrue(config('adminlte.sidebar_nav_accordion'));
        $settings=collect(config('adminlte.menu'))->firstWhere('key','settings');
        $this->assertContains('request-audits',$this->destinations([$settings]));
        Http::assertNothingSent();
    }

    #[DataProvider('clientAccess')]
    public function test_the_single_cos_link_preserves_each_original_permission_path(array $permissions,bool $visible): void
    {
        $destinations=$this->destinations($this->menu($this->actor($permissions)));
        $this->assertSame($visible,in_array('users',$destinations,true));
        $this->assertLessThanOrEqual(1,count(array_filter($destinations,fn($d)=>$d==='users')));
    }
    public static function clientAccess(): array
    {
        return [[[],false],[['crud.users.index'],true],[['users.viewlist'],true],[['crud.clients.index'],false],
            [['genealogista'],false],[['genealogista','crud.clients.index'],true],[['produccion','crud.clients.index'],true]];
    }

    #[DataProvider('receiptAccess')]
    public function test_moving_receipts_does_not_remove_the_original_parent_permission(array $permissions,bool $visible): void
    {
        $this->assertSame($visible,in_array('comprobantes',$this->destinations($this->menu($this->actor($permissions))),true));
    }
    public static function receiptAccess(): array
    {
        return [[[],false],[['crud.comprobantes.index'],false],[['admin.payments'],false],[['admin.payments','crud.comprobantes.index'],true]];
    }

    public function test_stripe_stays_in_finance_and_kalendia_in_agenda_with_their_existing_permissions(): void
    {
        $user=$this->actor(['administrador','crud.stripeverify.index'],'Administrador');
        $menu=$this->menu($user);
        $agenda=collect($menu)->firstWhere('key','agenda');
        $finance=collect($menu)->firstWhere('key','finance');
        $this->assertContains('admin.kalendia.appointments',$this->destinations([$agenda]));
        $this->assertNotContains('stripeverify',$this->destinations([$finance]));
        Permission::findOrCreate('admin.payments'); $user->givePermissionTo('admin.payments');
        $finance=collect($this->menu($user))->firstWhere('key','finance');
        $this->assertContains('stripeverify',$this->destinations([$finance]));
        $this->assertContains('listLatestStripeData',$this->destinations([$finance]));
    }

    public function test_client_navigation_is_preserved_and_internal_sections_are_hidden(): void
    {
        $menu=$this->menu($this->actor([],'Cliente'));
        $this->assertSame([],array_values(array_filter($menu,fn($i)=>isset($i['key']))));
        $destinations=$this->destinations($menu);
        foreach(['clientes.meetings','clientes.consultations','clientes.support-ticket.create','service-store.index','user/profile','clientes.salir'] as $route) {
            $this->assertContains($route,$destinations);
        }
    }

    public function test_the_payment_review_keeps_its_separate_window_and_proposals_keep_both_permission_paths(): void
    {
        $items=$this->leaves(config('adminlte.menu'));
        $review=collect($items)->firstWhere('route','crud.finance.payment-reviews');
        $this->assertSame('_blank',$review['target']);
        foreach(['administrador','coordventas'] as $permission) {
            $destinations=$this->destinations($this->menu($this->actor([$permission])));
            $this->assertContains('strategic-suggestions',$destinations);
            $this->assertSame(1,count(array_filter($destinations,fn($d)=>$d==='strategic-suggestions')));
        }
    }
}
