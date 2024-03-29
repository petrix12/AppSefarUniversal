<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call(RoleSeeder::class);
        $this->call(UserSeeder::class);
        $this->call(CountrySeeder::class);
        $this->call(AgclienteSeeder::class);
        $this->call(ParentescoSeeder::class);
        $this->call(LadoSeeder::class);
        $this->call(ConnectionSeeder::class);
        $this->call(TFileSeeder::class);
        $this->call(FormatSeeder::class);
        $this->call(HsReferidosSeeder::class);
    }
}
