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
        Role::create(['name' => 'Traviesoevans']);

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
                
        Permission::create(['name' => 'crud.agclientes.index'])->syncRoles($rolAdministrador, $rolGenealogista, $rolCliente);
        Permission::create(['name' => 'crud.agclientes.create'])->syncRoles($rolAdministrador, $rolGenealogista, $rolCliente);
        Permission::create(['name' => 'crud.agclientes.edit'])->syncRoles($rolAdministrador, $rolGenealogista, $rolCliente);
        Permission::create(['name' => 'crud.agclientes.destroy'])->syncRoles($rolAdministrador);

        Permission::create(['name' => 'consultas.onidex.index'])->syncRoles($rolAdministrador, $rolGenealogista);
        Permission::create(['name' => 'consultas.onidex.show'])->syncRoles($rolAdministrador, $rolGenealogista);  
              
        Permission::create(['name' => 'crud.parentescos.index'])->syncRoles($rolAdministrador, $rolGenealogista);
        Permission::create(['name' => 'crud.parentescos.create'])->syncRoles($rolAdministrador, $rolGenealogista);
        Permission::create(['name' => 'crud.parentescos.edit'])->syncRoles($rolAdministrador, $rolGenealogista);
        Permission::create(['name' => 'crud.parentescos.destroy'])->syncRoles($rolAdministrador);

        Permission::create(['name' => 'crud.lados.index'])->syncRoles($rolAdministrador, $rolGenealogista);
        Permission::create(['name' => 'crud.lados.create'])->syncRoles($rolAdministrador, $rolGenealogista);
        Permission::create(['name' => 'crud.lados.edit'])->syncRoles($rolAdministrador, $rolGenealogista);
        Permission::create(['name' => 'crud.lados.destroy'])->syncRoles($rolAdministrador);

        Permission::create(['name' => 'crud.connections.index'])->syncRoles($rolAdministrador, $rolGenealogista);
        Permission::create(['name' => 'crud.connections.create'])->syncRoles($rolAdministrador, $rolGenealogista);
        Permission::create(['name' => 'crud.connections.edit'])->syncRoles($rolAdministrador, $rolGenealogista);
        Permission::create(['name' => 'crud.connections.destroy'])->syncRoles($rolAdministrador);

        Permission::create(['name' => 'crud.families.index'])->syncRoles($rolAdministrador, $rolGenealogista);
        Permission::create(['name' => 'crud.families.create'])->syncRoles($rolAdministrador, $rolGenealogista);
        Permission::create(['name' => 'crud.families.edit'])->syncRoles($rolAdministrador, $rolGenealogista);
        Permission::create(['name' => 'crud.families.destroy'])->syncRoles($rolAdministrador);

        Permission::create(['name' => 'crud.files.index'])->syncRoles($rolAdministrador, $rolGenealogista,$rolDocumentalista,$rolProduccion,$rolCliente);
        Permission::create(['name' => 'crud.files.create'])->syncRoles($rolAdministrador, $rolGenealogista,$rolDocumentalista,$rolProduccion,$rolCliente);
        Permission::create(['name' => 'crud.files.edit'])->syncRoles($rolAdministrador, $rolGenealogista,$rolDocumentalista,$rolProduccion,$rolCliente);
        Permission::create(['name' => 'crud.files.destroy'])->syncRoles($rolAdministrador);

        Permission::create(['name' => 'crud.t_files.index'])->syncRoles($rolAdministrador, $rolGenealogista);
        Permission::create(['name' => 'crud.t_files.create'])->syncRoles($rolAdministrador, $rolGenealogista);
        Permission::create(['name' => 'crud.t_files.edit'])->syncRoles($rolAdministrador, $rolGenealogista);
        Permission::create(['name' => 'crud.t_files.destroy'])->syncRoles($rolAdministrador);

        Permission::create(['name' => 'administrar.documentos'])->syncRoles($rolAdministrador, $rolGenealogista,$rolDocumentalista,$rolProduccion);

        Permission::create(['name' => 'crud.libraries.index'])->syncRoles($rolAdministrador,$rolGenealogista,$rolDocumentalista);
        Permission::create(['name' => 'crud.libraries.create'])->syncRoles($rolAdministrador,$rolGenealogista,$rolDocumentalista);
        Permission::create(['name' => 'crud.libraries.edit'])->syncRoles($rolAdministrador,$rolGenealogista,$rolDocumentalista);
        Permission::create(['name' => 'crud.libraries.destroy'])->syncRoles($rolAdministrador);
        
        Permission::create(['name' => 'crud.formats.index'])->syncRoles($rolAdministrador);
        Permission::create(['name' => 'crud.formats.create'])->syncRoles($rolAdministrador);
        Permission::create(['name' => 'crud.formats.edit'])->syncRoles($rolAdministrador);
        Permission::create(['name' => 'crud.formats.destroy'])->syncRoles($rolAdministrador);

        Permission::create(['name' => 'crud.books.index'])->syncRoles($rolAdministrador,$rolGenealogista,$rolDocumentalista);
        Permission::create(['name' => 'crud.books.create'])->syncRoles($rolAdministrador,$rolGenealogista,$rolDocumentalista);
        Permission::create(['name' => 'crud.books.edit'])->syncRoles($rolAdministrador,$rolGenealogista,$rolDocumentalista);
        Permission::create(['name' => 'crud.books.destroy'])->syncRoles($rolAdministrador);

        Permission::create(['name' => 'crud.miscelaneos.index'])->syncRoles($rolAdministrador,$rolGenealogista,$rolDocumentalista);
        Permission::create(['name' => 'crud.miscelaneos.create'])->syncRoles($rolAdministrador,$rolGenealogista,$rolDocumentalista);
        Permission::create(['name' => 'crud.miscelaneos.edit'])->syncRoles($rolAdministrador,$rolGenealogista,$rolDocumentalista);
        Permission::create(['name' => 'crud.miscelaneos.destroy'])->syncRoles($rolAdministrador);
    }
}
