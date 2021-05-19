<?php

namespace App\Http\Livewire\Vistas\Arbol;

use App\Models\Agcliente;
use App\Models\Country;
use App\Models\Family;
use Livewire\Component;
use Illuminate\Http\Request;

class AlberoVista extends Component
{
    protected $queryString = [
        'LineaGenealogica' => ['except' => '']
    ];

    public $LineaGenealogica = 16;
    public $IDCliente;
    public $IDFamiliar;

    public function render()
    {
        $agclientes = Agcliente::where('IDCliente','LIKE',"%$this->IDCliente%")->get();
        $families = Family::where('IDCliente','LIKE',"%$this->IDCliente%")->get();
        $countries = Country::all();
        return view('livewire.vistas.arbol.albero-vista', compact('agclientes', 'families','countries'));
    }
}
