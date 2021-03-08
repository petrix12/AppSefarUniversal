<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        User::create([
            'name' => 'Pedro Bazó',
            'email' => 'bazo.pedro@gmail.com',
            'password' => bcrypt('12345678')
        ])->assignRole('Administrador');

        User::create([
            'name' => 'Prueba Genealogista',
            'email' => 'genealogista@gmail.com',
            'password' => bcrypt('12345678')
        ])->assignRole('Genealogista');

        User::create([
            'name' => 'Prueba Documentalista',
            'email' => 'documentalista@gmail.com',
            'password' => bcrypt('12345678')
        ])->assignRole('Documentalista');

        User::create([
            'name' => 'Prueba Producción',
            'email' => 'produccion@gmail.com',
            'password' => bcrypt('12345678')
        ])->assignRole('Produccion');

        User::create([
            'name' => 'Prueba cliente',
            'email' => 'cliente@gmail.com',
            'password' => bcrypt('12345678')
        ])->assignRole('Cliente');

        User::create([
            'name' => 'Prueba Sin Rol',
            'email' => 'sinrol@gmail.com',
            'password' => bcrypt('12345678')
        ]);

        User::factory(99)->create();
    }
}
