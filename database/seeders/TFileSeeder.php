<?php

namespace Database\Seeders;

use App\Models\TFile;
use Illuminate\Database\Seeder;

class TFileSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        TFile::create(['tipo' => 'Nacimiento','Notas' => 'Documentos relaciones con los datos de nacimiento de una persona']);
        TFile::create(['tipo' => 'Bautizo','Notas' => 'Documentos relaciones con los datos de bautizo de una persona']);
        TFile::create(['tipo' => 'Matrimonio','Notas' => 'Documentos relaciones con los datos de matrimonio de una persona']);
        TFile::create(['tipo' => 'Defunción','Notas' => 'Documentos relaciones con los datos de defunción de una persona']);
        TFile::create(['tipo' => 'Identificación','Notas' => 'Documentos relaciones con la identidad de una persona']);
        TFile::create(['tipo' => 'Filiatorio','Notas' => 'Documentos cuyo fin son expresamente filiatorios']);
        TFile::create(['tipo' => 'Otros','Notas' => 'Cualquier otro tipo de documentos']);
    }
}
