<?php

namespace Database\Seeders;

use App\Models\Connection;
use Illuminate\Database\Seeder;

class ConnectionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Connection::create(['Conexion' => 'PM','Significado' => 'Padre y Madre']);
        Connection::create(['Conexion' => 'P','Significado' => 'Padre']);
        Connection::create(['Conexion' => 'M','Significado' => 'Madre']);
        Connection::create(['Conexion' => 'APO','Significado' => 'Abuelo Paterno']);
        Connection::create(['Conexion' => 'APA','Significado' => 'Abuela Paterna']);
        Connection::create(['Conexion' => 'AMO','Significado' => 'Abuelo Materno']);
        Connection::create(['Conexion' => 'AMA','Significado' => 'Abuela Materna']);
        Connection::create(['Conexion' => 'BPPO','Significado' => 'Bisabuelo PP']);
        Connection::create(['Conexion' => 'BPPA','Significado' => 'Bisabuela PP']);
        Connection::create(['Conexion' => 'BPMO','Significado' => 'Bisabuelo PM']);
        Connection::create(['Conexion' => 'BPMA','Significado' => 'Bisabuela PM']);
        Connection::create(['Conexion' => 'BMPO','Significado' => 'Bisabuelo MP']);
        Connection::create(['Conexion' => 'BMPA','Significado' => 'Bisabuela MP']);
        Connection::create(['Conexion' => 'BMMO','Significado' => 'Bisabuelo MM']);
        Connection::create(['Conexion' => 'BMMA','Significado' => 'Bisabuela MM']);
        Connection::create(['Conexion' => 'TPPPO','Significado' => 'Tatarubuelo PPP']);
        Connection::create(['Conexion' => 'TPPPA','Significado' => 'Tatarubuela PPP']);
        Connection::create(['Conexion' => 'TPPMO','Significado' => 'Tatarubuelo PPM']);
        Connection::create(['Conexion' => 'TPPMA','Significado' => 'Tatarubuela PPM']);
        Connection::create(['Conexion' => 'TPMPO','Significado' => 'Tatarubuelo PMP']);
        Connection::create(['Conexion' => 'TPMPA','Significado' => 'Tatarubuela PMP']);
        Connection::create(['Conexion' => 'TPMMO','Significado' => 'Tatarubuelo PMM']);
        Connection::create(['Conexion' => 'TPMMA','Significado' => 'Tatarubuela PMM']);
        Connection::create(['Conexion' => 'TMPPO','Significado' => 'Tatarubuelo MPP']);
        Connection::create(['Conexion' => 'TMPPA','Significado' => 'Tatarubuela MPP']);
        Connection::create(['Conexion' => 'TMPMO','Significado' => 'Tatarubuelo MPM']);
        Connection::create(['Conexion' => 'TMPMA','Significado' => 'Tatarubuela MPM']);
        Connection::create(['Conexion' => 'TMMPO','Significado' => 'Tatarubuelo MMP']);
        Connection::create(['Conexion' => 'TMMPA','Significado' => 'Tatarubuela MMP']);
        Connection::create(['Conexion' => 'TMMMO','Significado' => 'Tatarubuelo MMM']);
        Connection::create(['Conexion' => 'TMMMA','Significado' => 'Tatarubuela MMM']);
        Connection::create(['Conexion' => 'C','Significado' => 'Cónyuge']);
        Connection::create(['Conexion' => 'ND','Significado' => 'No determinado']);
    }
}
