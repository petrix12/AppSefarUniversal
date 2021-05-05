<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Format;

class FormatSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Format::create(['formato' => 'DOC','ubicacion' => 'imagenes\formatos\DOC.png']);
        Format::create(['formato' => 'DOCX','ubicacion' => 'imagenes\formatos\DOCX.png']);
        Format::create(['formato' => 'FP7','ubicacion' => 'imagenes\formatos\FP7.png']);
        Format::create(['formato' => 'FTMB','ubicacion' => 'imagenes\formatos\FTMB.png']);
        Format::create(['formato' => 'JPG','ubicacion' => 'imagenes\formatos\JPG.png']);
        Format::create(['formato' => 'LNK','ubicacion' => 'imagenes\formatos\LNK.png']);
        Format::create(['formato' => 'MDB','ubicacion' => 'imagenes\formatos\MDB.png']);
        Format::create(['formato' => 'PDF','ubicacion' => 'imagenes\formatos\PDF.png']);
        Format::create(['formato' => 'Varios','ubicacion' => 'imagenes\formatos\Varios.png']);
        Format::create(['formato' => 'XLSX','ubicacion' => 'imagenes\formatos\XLSX.png']);     
    }
}
