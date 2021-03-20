<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Country;

class CountrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Country::create(['pais' => 'aanull','store' => 'imagenes\paises\aanull.png']);
        Country::create(['pais' => 'Alemania','store' => 'imagenes\paises\Alemania.png']);
        Country::create(['pais' => 'Argentina','store' => 'imagenes\paises\Argentina.png']);
        Country::create(['pais' => 'Australia','store' => 'imagenes\paises\Australia.png']);
        Country::create(['pais' => 'Bolivia','store' => 'imagenes\paises\Bolivia.png']);
        Country::create(['pais' => 'Brasil','store' => 'imagenes\paises\Brasil.png']);
        Country::create(['pais' => 'Bélgica','store' => 'imagenes\paises\Bélgica.png']);
        Country::create(['pais' => 'Canadá','store' => 'imagenes\paises\Canadá.png']);
        Country::create(['pais' => 'Chile','store' => 'imagenes\paises\Chile.png']);
        Country::create(['pais' => 'Chile2','store' => 'imagenes\paises\Chile2.png']);
        Country::create(['pais' => 'Colombia','store' => 'imagenes\paises\Colombia.png']);
        Country::create(['pais' => 'Costa Rica','store' => 'imagenes\paises\Costa Rica.png']);
        Country::create(['pais' => 'Cuba','store' => 'imagenes\paises\Cuba.png']);
        Country::create(['pais' => 'Curazao','store' => 'imagenes\paises\Curazao.png']);
        Country::create(['pais' => 'Ecuador','store' => 'imagenes\paises\Ecuador.png']);
        Country::create(['pais' => 'EEUU','store' => 'imagenes\paises\EEUU.png']);
        Country::create(['pais' => 'El Salvador','store' => 'imagenes\paises\El Salvador.png']);
        Country::create(['pais' => 'Emiratos Arabes Unidos','store' => 'imagenes\paises\Emiratos Arabes Unidos.png']);
        Country::create(['pais' => 'España','store' => 'imagenes\paises\España.png']);
        Country::create(['pais' => 'Francia','store' => 'imagenes\paises\Francia.png']);
        Country::create(['pais' => 'Holanda','store' => 'imagenes\paises\Holanda.png']);
        Country::create(['pais' => 'Inglaterra','store' => 'imagenes\paises\Inglaterra.png']);
        Country::create(['pais' => 'Italia','store' => 'imagenes\paises\Italia.png']);
        Country::create(['pais' => 'Líbano','store' => 'imagenes\paises\Líbano.png']);
        Country::create(['pais' => 'México','store' => 'imagenes\paises\México.png']);
        Country::create(['pais' => 'Nicaragua','store' => 'imagenes\paises\Nicaragua.png']);
        Country::create(['pais' => 'Panamá','store' => 'imagenes\paises\Panamá.png']);
        Country::create(['pais' => 'Perú','store' => 'imagenes\paises\Perú.png']);
        Country::create(['pais' => 'Puerto Rico','store' => 'imagenes\paises\Puerto Rico.png']);
        Country::create(['pais' => 'República Dominicana','store' => 'imagenes\paises\República Dominicana.png']);
        Country::create(['pais' => 'Suecia','store' => 'imagenes\paises\Suecia.png']);
        Country::create(['pais' => 'Venezuela','store' => 'imagenes\paises\Venezuela.png']);
    }
}
