<?php

namespace Database\Seeders;

use App\Models\Parentesco;
use Illuminate\Database\Seeder;

class ParentescoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Parentesco::create(['Parentesco' => 'Abuelo(a)','Inverso' => 'Nieto(a)']);
        Parentesco::create(['Parentesco' => 'Cónyuge','Inverso' => 'Cónyuge']);
        Parentesco::create(['Parentesco' => 'Cuñado(a)','Inverso' => 'Cuñado(a)']);
        Parentesco::create(['Parentesco' => 'Hermano(a)','Inverso' => 'Hermano(a)']);
        Parentesco::create(['Parentesco' => 'Hijo(a)','Inverso' => 'Padre-Madre']);
        Parentesco::create(['Parentesco' => 'Medio(a) hermano(a)','Inverso' => 'Medio(a) hermano(a)']);
        Parentesco::create(['Parentesco' => 'Nieto(a)','Inverso' => 'Abuelo(a)']);
        Parentesco::create(['Parentesco' => 'Padre-Madre','Inverso' => 'Hijo(a)']);
        Parentesco::create(['Parentesco' => 'Primo(a)','Inverso' => 'Primo(a)']);
        Parentesco::create(['Parentesco' => 'Primo(a) lejano(a)','Inverso' => 'Primo(a) lejano(a)']);
        Parentesco::create(['Parentesco' => 'Sobrino(a)','Inverso' => 'Tío(a)']);
        Parentesco::create(['Parentesco' => 'Sobrino(a) nieto(a)','Inverso' => 'Tío(a) abuelo(a)']);
        Parentesco::create(['Parentesco' => 'Sospecha','Inverso' => 'Sospecha']);
        Parentesco::create(['Parentesco' => 'Suegro(a)','Inverso' => 'Yerno-Nuera']);
        Parentesco::create(['Parentesco' => 'Tío(a)','Inverso' => 'Sobrino(a)']);
        Parentesco::create(['Parentesco' => 'Tío(a) abuelo(a)','Inverso' => 'Sobrino(a) nieto(a)']);
        Parentesco::create(['Parentesco' => 'Yerno-Nuera','Inverso' => 'Suegro(a)']);
    }
}
