<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Http, Mail};
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
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
        foreach (['mystatus','meetings','personal_data','change-history','formulario-001','adminchangepassword','familiars','payments',
            'paymentspen','admin-req','documents','etiquetado','negocios','teamleader-migration','client-tasks','client-chat'] as $target) {
            $this->assertCount(1,$xpath->query('//*[@data-bs-target="#'.$target.'"]'),$target);
        }
        $this->assertStringContainsString('client-notes-link',$html); Http::assertNothingSent();
    }
    public function test_clients_only_receive_their_original_options_without_internal_controls(): void
    {
        $html=view('crud.users.partials.workspace-navigation',$this->data(false) + ['basicCosEditor' => ['roles' => []]])->render(); $xpath=$this->document($html);
        foreach (['mystatus','meetings','personal_data','mypassword','familiars','payments','paymentspen','client-req','documents'] as $target) {
            $this->assertCount(1,$xpath->query('//*[@data-bs-target="#'.$target.'"]'));
        }
        foreach (['teamleader-migration-tab','client-tasks-tab','client-chat-tab','change-history-tab','formulario-001-tab','admin-req-tab','basic-edit-tab'] as $id) {
            $this->assertCount(0,$xpath->query('//*[@id="'.$id.'"]'));
        }
    }

    public function test_basic_editor_reuses_the_update_route_and_direct_permissions_inside_profile(): void
    {
        $this->basicEditorSchema();
        $data = $this->data(true);
        $user = $data['user'];
        $inherited = Permission::findOrCreate('sample.inherited');
        $direct = Permission::findOrCreate('sample.direct');
        Role::findByName('Cliente')->givePermissionTo($inherited);
        $user->givePermissionTo($direct);
        auth()->user()->givePermissionTo(Permission::findOrCreate('crud.users.index'));

        $standalone = $this->get(route('crud.users.editBasic', $user))->assertOk();
        $editor = $standalone->viewData('userPermissionNames');
        $this->assertSame([$direct->name], $editor);
        $formData = $standalone->original->getData();
        $navigation = $this->document(view('crud.users.partials.workspace-navigation', $data + ['basicCosEditor' => $formData])->render());
        $this->assertCount(1, $navigation->query('//*[@id="basic-edit-tab" and @data-bs-target="#basic-edit" and @aria-selected="false"]'));
        $form = $this->document(view('crud.users.partials.basic-editor', $formData + ['embeddedInCos' => true])->render());
        $this->assertSame(route('crud.users.updateBasic', $user), $form->evaluate('string(//form/@action)'));
        $this->assertCount(1, $form->query('//form//input[@name="return_to_cos" and @value="1"]'));
        $this->assertCount(1, $form->query('//input[@name="permissions[]" and @value="sample.direct" and @checked]'));
        $this->assertCount(0, $form->query('//input[@name="permissions[]" and @value="sample.inherited" and @checked]'));

        $this->put(route('crud.users.updateBasic', $user), [
            'return_to_cos' => 1, 'name' => $user->name, 'nombres' => $user->nombres,
            'apellidos' => $user->apellidos, 'email' => $user->email, 'phone' => $user->phone, 'passport' => $user->passport,
            'hs_id' => '19232', 'tl_id' => 'teamleader-contact', 'roles' => ['Cliente'], 'permissions' => [$direct->name],
        ])->assertRedirect(route('crud.users.edit', $user).'#basic-edit');
        $user->refresh();
        $this->assertSame('19232', $user->hs_id);
        $this->assertSame('teamleader-contact', $user->tl_id);
        $this->assertSame([$direct->name], $user->permissions->pluck('name')->all());
        Http::assertNothingSent();
    }

    public function test_basic_editor_validation_returns_to_the_same_cos_tab_without_retaining_passwords(): void
    {
        $this->basicEditorSchema();
        $user = $this->data(true)['user'];
        auth()->user()->givePermissionTo(Permission::findOrCreate('crud.users.index'));

        $this->put(route('crud.users.updateBasic', $user), [
            'return_to_cos' => 1, 'hs_id' => '19232', 'password' => 'temporary-password',
        ])->assertRedirect(route('crud.users.edit', $user).'#basic-edit')
            ->assertSessionHasErrorsIn('cosBasic', ['name', 'password'])->assertSessionHasInput('hs_id', '19232')
            ->assertSessionMissing('_old_input.password');
    }

    public function test_regular_staff_cannot_open_or_submit_the_basic_editor(): void
    {
        $user = $this->data(false)['user'];
        $staffRole = Role::findOrCreate('Ventas');
        $staff = User::factory()->create()->syncRoles([$staffRole]);
        $staff->givePermissionTo(Permission::findOrCreate('crud.users.index'));

        $this->actingAs($staff)->get(route('crud.users.editBasic', $user))->assertForbidden();
        $this->put(route('crud.users.updateBasic', $user), ['return_to_cos' => 1])->assertForbidden();
    }

    private function basicEditorSchema(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->string('tl_id')->nullable());
        $this->artisan('migrate', ['--path' => 'database/migrations/2026_09_28_120000_add_genealogy_tree_id_to_users_table.php'])->assertSuccessful();
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
        $this->assertStringContainsString('Ficha del representado',$internal);
        $this->assertStringContainsString('https://app.hubspot.com/contacts/20053496/record/0-1/123',$internal);
        $this->assertStringContainsString('https://focus.teamleader.eu/contacts/contact-123',$internal);
        Http::assertNothingSent();
    }
    public function test_profile_leaves_invalid_dates_empty_and_keeps_valid_legacy_dates(): void
    {
        $data = $this->data(true);
        $data['user']->forceFill([
            'date_of_birth' => '29/02/2000',
            'fecha_de_caducidad_del_pasaporte' => '-0001-11-30',
            'n2__fecha_aacs_notificacion_aprobado' => '0000-00-00',
            'n3__fcje_registro' => '2026-02-30',
            'n3__fecha_de_recordatorio' => '2026-11-26 00:00:00',
        ]);
        $page = $this->document(view('crud.users.partials.profile-details', $data)->render());
        $this->assertSame('2000-02-29', $page->evaluate('string(//*[@id="date_of_birth"]/@value)'));
        $this->assertSame('2026-11-26', $page->evaluate('string(//*[@id="n3__fecha_de_recordatorio"]/@value)'));
        foreach (['fecha_de_caducidad_del_pasaporte', 'tl-custom-fecha_de_caducidad_del_pasaporte', 'n2__fecha_aacs_notificacion_aprobado', 'n3__fcje_registro'] as $field) {
            $input = $page->query('//*[@id="'.$field.'"]')->item(0);
            $this->assertNotNull($input);
            $this->assertSame('', $input->getAttribute('value'));
        }
    }
    public function test_the_client_preview_button_and_return_control_are_preserved(): void
    {
        foreach ([false,true] as $preview) {
            $html=view('crud.users.partials.preview-control',[
                'cosClientPreview'=>$preview,'cosPreviewTargetUrl'=>'/users/123/edit?vista_cliente='.($preview?'0':'1'),
                'cosPreviewButtonLabel'=>$preview?'Volver a vista interna':'Ver como representado','cosPreviewLoadingLabel'=>'Cargando vista…'
            ])->render();
            $xpath=$this->document($html); $toggle=$xpath->query('//*[@id="cos-preview-toggle"]');
            $this->assertCount(1,$toggle);
            $this->assertSame($preview?'true':'false',$toggle->item(0)->getAttribute('aria-checked'));
            $this->assertStringContainsString($preview?'vista_cliente=0':'vista_cliente=1',$toggle->item(0)->getAttribute('href'));
            $this->assertStringContainsString($preview?'Volver a vista interna':'Ver como representado',$html);
            if ($preview) $this->assertStringContainsString('Vista del representado',$html);
        }
    }
}
