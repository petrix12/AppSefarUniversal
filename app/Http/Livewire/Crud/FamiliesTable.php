<?php

namespace App\Http\Livewire\Crud;

use App\Models\Family;
use Livewire\Component;
use Livewire\WithPagination;

class FamiliesTable extends Component
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
        $families = Family::where('IDCombinado','LIKE',"%$this->search%")
            ->orWhere('Cliente','LIKE',"%$this->search%")
            ->orWhere('Familiar','LIKE',"%$this->search%")
            ->orWhere('Parentesco','LIKE',"%$this->search%")
            ->orWhere('PaisNac','LIKE',"%$this->search%")
            ->orWhere('LugarNac','LIKE',"%$this->search%")
            ->orderBy('Cliente','ASC')
            ->paginate($this->perPage);
        return view('livewire.crud.families-table', compact('families'));
    }

    public function clear(){
        $this->search = '';
        $this->page = 1;
        $this->perPage = '15';
    }
}
