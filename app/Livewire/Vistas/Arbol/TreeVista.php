<?php

namespace App\Livewire\Vistas\Arbol;

use App\Models\Agcliente;
use App\Models\Country;
use App\Models\Family;
use App\Models\TFile;
use Livewire\Component;
use Illuminate\Http\Request;

class TreeVista extends Component
{
    public $LineaGenealogica = 16;
    public $IDCliente;
    public $IDFamiliar;
    public $Modo=1;

    public function render()
    {
        $agclientes = Agcliente::where('IDCliente','LIKE',"%$this->IDCliente%")->get();
        $families = Family::where('IDCliente','LIKE',"%$this->IDCliente%")->get();
        $countries = Country::all();
        /* $t_files = TFile::all(); */
        return view('livewire.vistas.arbol.tree-vista', compact('agclientes', 'families', 'countries'/* , 't_files' */));
    }
}
