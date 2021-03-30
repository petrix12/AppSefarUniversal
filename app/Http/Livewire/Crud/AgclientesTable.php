<?php

namespace App\Http\Livewire\Crud;

use App\Models\Agcliente;
use Livewire\WithPagination;
use Livewire\Component;

class AgclientesTable extends Component
{
    use WithPagination;

    protected $queryString = [
        'search' => ['except' => ''],
        'perPage' => ['except' => '15']
    ];

    public $search = '';
    public $perPage = '15';
    
    public function render()
    {
        $agclientes = Agcliente::where('IDCliente','LIKE',"%$this->search%")
                ->orWhere('Nombres','LIKE',"%$this->search%")
                ->orWhere('Apellidos','LIKE',"%$this->search%")
                ->orWhere('NPasaporte','LIKE',"%$this->search%")
                ->orWhere('PaisPasaporte','LIKE',"%$this->search%")
                ->orWhere('NDocIdent','LIKE',"%$this->search%")
                ->orWhere('PaisDocIdent','LIKE',"%$this->search%")
                ->orWhere('PaisDocIdent','LIKE',"%$this->search%")
                ->orWhere('LugarNac','LIKE',"%$this->search%")
                ->orWhere('PaisNac','LIKE',"%$this->search%")
                ->orWhere('LugarBtzo','LIKE',"%$this->search%")
                ->orWhere('PaisBtzo','LIKE',"%$this->search%")
                ->orWhere('LugarMatr','LIKE',"%$this->search%")
                ->orWhere('PaisMatr','LIKE',"%$this->search%")
                ->orWhere('PaisDef','LIKE',"%$this->search%")
                ->orWhere('Observaciones','LIKE',"%$this->search%")
                ->orWhere('NombresF','LIKE',"%$this->search%")
                ->orWhere('ApellidosF','LIKE',"%$this->search%")
                ->orWhere('NPasaporteF','LIKE',"%$this->search%")
                ->orWhere('PNacimiento','LIKE',"%$this->search%")
                ->orWhere('LNacimiento','LIKE',"%$this->search%")
                ->orWhere('Usuario','LIKE',"%$this->search%")
                ->orderBy('IDCliente','ASC')
                ->paginate($this->perPage);
                //dd($agclientes);

        return view('livewire.crud.agclientes-table', compact('agclientes')/* [
            'agclientes' => Agcliente::where('IDCliente','LIKE',"%$this->search%")
                ->orWhere('Nombres','LIKE',"%$this->search%")
                ->orWhere('Apellidos','LIKE',"%$this->search%")
                ->orWhere('NPasaporte','LIKE',"%$this->search%")
                ->orWhere('PaisPasaporte','LIKE',"%$this->search%")
                ->orWhere('NDocIdent','LIKE',"%$this->search%")
                ->orWhere('PaisDocIdent','LIKE',"%$this->search%")
                ->orWhere('PaisDocIdent','LIKE',"%$this->search%")
                ->orWhere('LugarNac','LIKE',"%$this->search%")
                ->orWhere('PaisNac','LIKE',"%$this->search%")
                ->orWhere('LugarBtzo','LIKE',"%$this->search%")
                ->orWhere('PaisBtzo','LIKE',"%$this->search%")
                ->orWhere('LugarMatr','LIKE',"%$this->search%")
                ->orWhere('PaisMatr','LIKE',"%$this->search%")
                ->orWhere('PaisDef','LIKE',"%$this->search%")
                ->orWhere('Observaciones','LIKE',"%$this->search%")
                ->orWhere('NombresF','LIKE',"%$this->search%")
                ->orWhere('ApellidosF','LIKE',"%$this->search%")
                ->orWhere('NPasaporteF','LIKE',"%$this->search%")
                ->orWhere('PNacimiento','LIKE',"%$this->search%")
                ->orWhere('LNacimiento','LIKE',"%$this->search%")
                ->orWhere('Usuario','LIKE',"%$this->search%")
                ->orderBy('IDCliente','ASC')
                ->paginate($this->perPage)
        ] */);
    }

    public function clear(){
        $this->search = '';
        $this->page = 1;
        $this->perPage = '15';
    }
}
