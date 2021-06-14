<?php

namespace App\Http\Livewire\Crud;

use App\Models\Miscelaneo;
use Livewire\WithPagination;
use Livewire\Component;

class MiscelaneosTable extends Component
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
        $miscelaneos = Miscelaneo::where('titulo','LIKE',"%$this->search%")
                        ->orWhere('autor','LIKE',"%$this->search%")
                        ->orWhere('publicado','LIKE',"%$this->search%")
                        ->orWhere('editorial','LIKE',"%$this->search%")
                        ->orWhere('volumen','LIKE',"%$this->search%")
                        ->orWhere('material','LIKE',"%$this->search%")
                        ->orWhere('paginacion','LIKE',"%$this->search%")
                        ->orWhere('isbn','LIKE',"%$this->search%")
                        ->orWhere('notas','LIKE',"%$this->search%")
                        ->orWhere('claves','LIKE',"%$this->search%")
                        ->orWhere('catalogador','LIKE',"%$this->search%")
                        ->orWhere('enlace','LIKE',"%$this->search%")
                        ->orderBy('updated_at','DESC')
                        ->paginate($this->perPage);
        return view('livewire.crud.miscelaneos-table', compact('miscelaneos'));
    }

    public function clear(){
        $this->search = '';
        $this->page = 1;
        $this->perPage = '10';
    }
}