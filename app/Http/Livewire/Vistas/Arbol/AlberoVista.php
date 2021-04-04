<?php

namespace App\Http\Livewire\Vistas\Arbol;

use App\Models\Agcliente;
use Livewire\Component;

class AlberoVista extends Component
{
    public $LineaGenealogica = 16;
    public $IDCliente;

    public function render()
    {
        $agclientes = Agcliente::where('IDCliente','LIKE',"%$this->IDCliente%")
            ->get();
        /* ->paginate(50); */
        return view('livewire.vistas.arbol.albero-vista', compact('agclientes'));
    }
}
