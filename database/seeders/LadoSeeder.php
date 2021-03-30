<?php

namespace Database\Seeders;

use App\Models\Lado;
use Illuminate\Database\Seeder;

class LadoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Lado::create(['Lado' => 'P','Significado' => 'Padre']);
        Lado::create(['Lado' => 'M','Significado' => 'Madre']);
        Lado::create(['Lado' => 'PM','Significado' => 'Padre y Madre']);
        Lado::create(['Lado' => 'C','Significado' => 'Cónyuge']);
        Lado::create(['Lado' => 'ND','Significado' => 'No determinado']);
    }
}
