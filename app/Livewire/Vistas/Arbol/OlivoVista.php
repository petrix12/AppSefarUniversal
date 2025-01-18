<?php

namespace App\Livewire\Vistas\Arbol;

use App\Models\Agcliente;
use App\Models\Family;
use Livewire\Component;

class OlivoVista extends Component
{
    public $IDCliente;
    public $IDFamiliar;

    public function render()
    {
        $agclientes = Agcliente::where('IDCliente','LIKE',"%$this->IDCliente%")->get();
        $families = Family::where('IDCliente','LIKE',"%$this->IDCliente%")->get();
        return view('livewire.vistas.arbol.olivo-vista', compact('agclientes', 'families'));
    }
}
