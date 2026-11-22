<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\{Http, Mail};
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CosWorkspacePresentationTest extends TestCase
{
    use \Tests\Concerns\RegistrationSchema;
    protected function setUp(): void
    {
        parent::setUp(); config(['cache.default'=>'array','session.driver'=>'array']);
        Http::preventStrayRequests(); Mail::fake(); $this->createRegistrationSchema(); Role::findOrCreate('Administrador');
    }
    private function data(bool $internal): array
    {
        $actor=User::factory()->create(); $actor->syncRoles([$internal?'Administrador':'Cliente']); $this->actingAs($actor);
        $user=User::factory()->create(['name'=>'Cliente de ejemplo','nombres'=>'María','apellidos'=>'Ejemplo','email'=>'cliente@example.invalid',
            'phone'=>'+000000000','passport'=>'PRUEBA-NO-VALIDA','pay'=>2,'contrato'=>1]);
        $user->syncRoles(['Cliente']);
        return ['user'=>$user,'cosViewRoleId'=>$internal?1:5,'canEditCos'=>$internal,'servicios'=>collect(), 'teamleaderProfileCustomValues'=>[]];
    }
    private function document(string $html): \DOMXPath
    {
        $document=new \DOMDocument; @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html); return new \DOMXPath($document);
    }
    public function test_navigation_preserves_all_existing_targets_and_only_the_status_tab_starts_active(): void
    {
        $html=view('crud.users.partials.workspace-navigation',$this->data(true))->render(); $xpath=$this->document($html);
        $active=$xpath->query('//*[@role="tab" and @aria-selected="true"]');
        $this->assertCount(1,$active); $this->assertSame('mystatus-tab',$active->item(0)->getAttribute('id'));
        foreach (['mystatus','personal_data','change-history','formulario-001','adminchangepassword','familiars','payments',
            'paymentspen','admin-req','documents','etiquetado','negocios','teamleader-migration','client-tasks','client-chat'] as $target) {
            $this->assertCount(1,$xpath->query('//*[@data-bs-target="#'.$target.'"]'),$target);
        }
        $this->assertStringContainsString('client-notes-link',$html); Http::assertNothingSent();
    }
    public function test_clients_only_receive_their_original_options_without_internal_controls(): void
    {
        $html=view('crud.users.partials.workspace-navigation',$this->data(false))->render(); $xpath=$this->document($html);
        foreach (['mystatus','personal_data','mypassword','familiars','payments','paymentspen','client-req','documents'] as $target) {
            $this->assertCount(1,$xpath->query('//*[@data-bs-target="#'.$target.'"]'));
        }
        foreach (['teamleader-migration-tab','client-tasks-tab','client-chat-tab','change-history-tab','formulario-001-tab','admin-req-tab'] as $id) {
            $this->assertCount(0,$xpath->query('//*[@id="'.$id.'"]'));
        }
    }
    public function test_profile_preserves_enabled_edit_fields_for_internal_staff_and_is_read_only_for_clients(): void
    {
        $data=$this->data(true); $html=view('crud.users.partials.profile-details',$data)->render(); $xpath=$this->document($html);
        foreach (['nombres','apellidos','email','phone','date_of_birth','genero','edo_civil','pay','servicio','contrato'] as $name) {
            $nodes=$xpath->query('//*[@name="'.$name.'" and not(ancestor::fieldset[@disabled])]'); $this->assertGreaterThan(0,$nodes->length,$name);
        }
        $this->assertCount(1,$xpath->query('//*[@id="guardar-datos"]'));
        $data['canEditCos']=false; $data['cosViewRoleId']=5;
        $readonly=$this->document(view('crud.users.partials.profile-details',$data)->render());
        $this->assertCount(0,$readonly->query('//*[@id="guardar-datos"]'));
        $this->assertCount(0,$readonly->query('//*[@id="datos-personales-form"]//*[self::input or self::textarea or self::select][not(@type="hidden") and not(@disabled) and not(ancestor::fieldset[@disabled])]'));
        Http::assertNothingSent();
    }
    public function test_the_header_uses_the_same_identity_and_hides_external_links_from_clients(): void
    {
        $data=$this->data(false); $data['user']->forceFill(['hs_id'=>'123','tl_id'=>'contact-123']);
        $html=view('crud.users.partials.workspace-header',$data)->render();
        $this->assertStringContainsString('María Ejemplo',$html); $this->assertStringNotContainsString('app.hubspot.com',$html);
        $data['cosViewRoleId']=1;
        $internal=view('crud.users.partials.workspace-header',$data)->render();
        $this->assertStringContainsString('https://app.hubspot.com/contacts/20053496/record/0-1/123',$internal);
        $this->assertStringContainsString('https://focus.teamleader.eu/contacts/contact-123',$internal);
        Http::assertNothingSent();
    }
    public function test_the_client_preview_button_and_return_control_are_preserved(): void
    {
        foreach ([false,true] as $preview) {
            $html=view('crud.users.partials.preview-control',[
                'cosClientPreview'=>$preview,'cosPreviewTargetUrl'=>'/users/123/edit?vista_cliente='.($preview?'0':'1'),
                'cosPreviewButtonLabel'=>$preview?'Volver a vista interna':'Ver como cliente','cosPreviewLoadingLabel'=>'Cargando vista…'
            ])->render();
            $xpath=$this->document($html); $toggle=$xpath->query('//*[@id="cos-preview-toggle"]');
            $this->assertCount(1,$toggle);
            $this->assertSame($preview?'true':'false',$toggle->item(0)->getAttribute('aria-checked'));
            $this->assertStringContainsString($preview?'vista_cliente=0':'vista_cliente=1',$toggle->item(0)->getAttribute('href'));
            $this->assertStringContainsString($preview?'Volver a vista interna':'Ver como cliente',$html);
        }
    }
}
