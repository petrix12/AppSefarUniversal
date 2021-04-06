<?php

namespace App\Http\Livewire\Vistas\Arbol;

use App\Models\Agcliente;
use App\Models\Family;
use Livewire\Component;
use Illuminate\Http\Request;

class TreeVista extends Component
{
    public $LineaGenealogica = 16;
    public $IDCliente;
    public $IDFamiliar;

    public function render()
    {
        $agclientes = Agcliente::where('IDCliente','LIKE',"%$this->IDCliente%")->get();
        $families = Family::where('IDCliente','LIKE',"%$this->IDCliente%")->get();
        return view('livewire.vistas.arbol.tree-vista', compact('agclientes', 'families'));
    }
}
