<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AssignClientRole extends Command
{
    protected $signature = 'users:assign-client-role';
    protected $description = 'Asignar rol cliente (ID 5) a usuarios que no tengan ningún rol';

    public function handle()
    {
        $role = Role::find(5);

        if (!$role) {
            $this->error('El rol con ID 5 no existe.');
            return self::FAILURE;
        }

        $users = User::doesntHave('roles')->get();

        if ($users->isEmpty()) {
            $this->info('No hay usuarios sin roles.');
            return self::SUCCESS;
        }

        $count = 0;

        foreach ($users as $user) {
            $user->assignRole($role);
            $count++;
            $this->line("Rol cliente asignado a: {$user->id} - {$user->email}");
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->info("Se asignó el rol cliente a {$count} usuarios sin roles.");

        return self::SUCCESS;
    }
}
