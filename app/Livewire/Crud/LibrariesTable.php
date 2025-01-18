<?php

namespace App\Livewire\Crud;

use App\Models\Library;
use Livewire\WithPagination;
use Livewire\Component;

class LibrariesTable extends Component
{
    use WithPagination;

    protected $queryString = [
        'search' => ['except' => ''],
        'perPage' => ['except' => '10']
    ];

    public $search = '';
    public $perPage = '10';
    
    public function render()
    {
        $libraries = Library::where('documento','LIKE',"%$this->search%")
                    ->orWhere('formato','LIKE',"%$this->search%")
                    ->orWhere('tipo','LIKE',"%$this->search%")
                    ->orWhere('fuente','LIKE',"%$this->search%")
                    ->orWhere('origen','LIKE',"%$this->search%")
                    ->orWhere('ubicacion','LIKE',"%$this->search%")
                    ->orWhere('ubicacion_ant','LIKE',"%$this->search%")
                    ->orWhere('busqueda','LIKE',"%$this->search%")
                    ->orWhere('notas','LIKE',"%$this->search%")
                    ->orWhere('pais','LIKE',"%$this->search%")
                    ->orWhere('ciudad','LIKE',"%$this->search%")
                    ->orWhere('responsabilidad','LIKE',"%$this->search%")
                    ->orWhere('edicion','LIKE',"%$this->search%")
                    ->orWhere('editorial','LIKE',"%$this->search%")
                    ->orWhere('no_vol','LIKE',"%$this->search%")
                    ->orWhere('coleccion','LIKE',"%$this->search%")
                    ->orWhere('colacion','LIKE',"%$this->search%")
                    ->orWhere('isbn','LIKE',"%$this->search%")
                    ->orWhere('serie','LIKE',"%$this->search%")
                    ->orWhere('no_clasificacion','LIKE',"%$this->search%")
                    ->orWhere('titulo_revista','LIKE',"%$this->search%")
                    ->orWhere('resumen','LIKE',"%$this->search%")
                    ->orderBy('updated_at','ASC')
                    ->paginate($this->perPage);
        return view('livewire.crud.libraries-table', compact('libraries'));
    }

    public function clear(){
        $this->search = '';
        $this->page = 1;
        $this->perPage = '10';
    }
}
