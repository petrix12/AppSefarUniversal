<?php

namespace App\Http\Livewire\Crud;

use App\Models\Agcliente;
use Illuminate\Support\Facades\DB;
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
    public $solo_clientes = true;
    public $ordenar = 'FRegistro';
    public $asc = 'DESC';
    
    public function render()
    {
        //$rol = Auth()->user()->hasRole('Traviesoevans');
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
                ->orWhere('referido','LIKE',"%$this->search%")
                ->orWhere(DB::raw("CONCAT(Nombres,' ',Apellidos)"), 'LIKE',"%$this->search%")
                ->orWhere(DB::raw("CONCAT(Apellidos,' ',Nombres)"), 'LIKE',"%$this->search%")
                ->rol()
                ->clientes($this->solo_clientes)
                ->orderBy($this->ordenar,$this->asc)
                ->orderBy('IDPersona','ASC')
                ->paginate($this->perPage);
       
        return view('livewire.crud.agclientes-table', compact('agclientes'));
    }

    public function clear(){
        $this->search = '';
        $this->page = 1;
        $this->perPage = '15';
    }

    public function limpiar_page(){
        $this->reset('page');
    }

    public function forma_ordenar(){
        $this->asc == 'ASC' ? $this->asc = 'DESC' : $this->asc = 'ASC';
    }
}
