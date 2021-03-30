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
        Permission::create(['name' => 'genealogista'])->syncRoles($rolAdministrador, $rolGenealogista);
        Permission::create(['name' => 'documentalista'])->syncRoles($rolAdministrador, $rolDocumentalista);
        Permission::create(['name' => 'produccion'])->syncRoles($rolAdministrador, $rolProduccion);
        Permission::create(['name' => 'cliente'])->syncRoles($rolAdministrador, $rolCliente);

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
        
        Permission::create(['name' => 'crud.countries.index'])->syncRoles($rolAdministrador);
        Permission::create(['name' => 'crud.countries.create'])->syncRoles($rolAdministrador);
        Permission::create(['name' => 'crud.countries.edit'])->syncRoles($rolAdministrador);
        Permission::create(['name' => 'crud.countries.destroy'])->syncRoles($rolAdministrador);
                
        Permission::create(['name' => 'crud.agclientes.index'])->syncRoles($rolAdministrador, $rolGenealogista);
        Permission::create(['name' => 'crud.agclientes.create'])->syncRoles($rolAdministrador, $rolGenealogista);
        Permission::create(['name' => 'crud.agclientes.edit'])->syncRoles($rolAdministrador, $rolGenealogista);
        Permission::create(['name' => 'crud.agclientes.destroy'])->syncRoles($rolAdministrador);

        Permission::create(['name' => 'consultas.onidex.index'])->syncRoles($rolAdministrador, $rolGenealogista);
        Permission::create(['name' => 'consultas.onidex.show'])->syncRoles($rolAdministrador, $rolGenealogista);  
              
        Permission::create(['name' => 'crud.parentescos.index'])->syncRoles($rolAdministrador, $rolGenealogista);
        Permission::create(['name' => 'crud.parentescos.create'])->syncRoles($rolAdministrador, $rolGenealogista);
        Permission::create(['name' => 'crud.parentescos.edit'])->syncRoles($rolAdministrador, $rolGenealogista);
        Permission::create(['name' => 'crud.parentescos.destroy'])->syncRoles($rolAdministrador, $rolGenealogista);
    }
}
