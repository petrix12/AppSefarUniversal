<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Roles
        $rolAdministrador = Role::create(['name' => 'Administrador']);
        $rolGenealogista = Role::create(['name' => 'Genealogista']);
        $rolDocumentalista = Role::create(['name' => 'Documentalista']);
        $rolProduccion = Role::create(['name' => 'Produccion']);
        $rolCliente = Role::create(['name' => 'Cliente']);

        Permission::create(['name' => 'administrador'])->syncRoles($rolAdministrador);

        Permission::create(['name' => 'crud.users.index'])->syncRoles($rolAdministrador);
        Permission::create(['name' => 'crud.users.create'])->syncRoles($rolAdministrador);
        Permission::create(['name' => 'crud.users.edit'])->syncRoles($rolAdministrador);
        Permission::create(['name' => 'crud.users.destroy'])->syncRoles($rolAdministrador);
        
        Permission::create(['name' => 'crud.roles.index'])->syncRoles($rolAdministrador);
        Permission::create(['name' => 'crud.roles.create'])->syncRoles($rolAdministrador);
        Permission::create(['name' => 'crud.roles.edit'])->syncRoles($rolAdministrador);
        Permission::create(['name' => 'crud.roles.destroy'])->syncRoles($rolAdministrador);
        
        Permission::create(['name' => 'crud.permissions.index'])->syncRoles($rolAdministrador);
        Permission::create(['name' => 'crud.permissions.create'])->syncRoles($rolAdministrador);
        Permission::create(['name' => 'crud.permissions.edit'])->syncRoles($rolAdministrador);
        Permission::create(['name' => 'crud.permissions.destroy'])->syncRoles($rolAdministrador);
    }
}
