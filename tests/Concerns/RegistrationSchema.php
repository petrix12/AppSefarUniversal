<?php

namespace Tests\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

trait RegistrationSchema
{
    protected function createRegistrationSchema(): void
    {
        $this->artisan('migrate', ['--path' => [
            'database/migrations/2014_10_12_000000_create_users_table.php',
            'database/migrations/2021_03_08_173429_create_permission_tables.php',
        ]])->assertExitCode(0);
        Role::findOrCreate('Cliente');
        Permission::findOrCreate('pay.services');
        Permission::findOrCreate('finish.register');
        Schema::table('users', function (Blueprint $t) {
            foreach (['cantidad_alzada', 'antepasados', 'vinculo_antepasados', 'contrato', 'cosready'] as $field) $t->integer($field)->nullable();
            $t->text('estado_de_datos_y_documentos_de_los_antepasados')->nullable();
        });
        Schema::create('servicios', function (Blueprint $t) {
            $t->id(); $t->string('nombre'); $t->string('id_hubspot'); $t->integer('precio')->default(99); $t->integer('tipov')->default(1); $t->timestamps();
        });
        Schema::create('compras', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('id_user'); $t->unsignedBigInteger('servicio_id')->nullable(); $t->string('servicio_hs_id')->nullable();
            $t->string('source')->nullable(); $t->text('descripcion')->nullable(); $t->integer('pagado')->default(0); $t->decimal('monto', 12, 2);
            $t->string('hash_factura')->nullable(); $t->unsignedBigInteger('deal_id')->nullable(); $t->json('metadata')->nullable(); $t->timestamp('paid_at')->nullable();
            $t->decimal('montooriginal', 12, 2)->nullable(); $t->integer('porcentajedescuento')->nullable(); $t->timestamps();
        });
        Schema::create('facturas', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('id_cliente'); $t->string('hash_factura'); $t->string('met'); $t->timestamps();
        });
        Schema::create('agclientes', function (Blueprint $t) {
            $t->id(); $t->integer('IDPersona');
            foreach (['IDCliente', 'Nombres', 'Apellidos', 'NPasaporte', 'PNacimiento', 'PaisNac', 'referido', 'FRegistro', 'FUpdate', 'Usuario'] as $field) $t->string($field)->nullable();
            $t->timestamps();
        });
        $this->artisan('migrate', ['--path' => 'database/migrations/2026_11_19_120100_create_registration_meeting_webhooks_table.php'])->assertExitCode(0);
    }
}
