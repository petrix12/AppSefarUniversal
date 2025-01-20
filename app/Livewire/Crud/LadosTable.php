<?php

namespace App\Livewire\Crud;

use App\Models\Lado;
use Livewire\Component;
use Livewire\WithPagination;

class LadosTable extends Component
{
    use WithPagination;

    protected $queryString = [
        'search' => ['except' => ''],
        'perPage' => ['except' => '5']
    ];

    public $search = '';
    public $perPage = '5';
    public $page = '1';

    public function render()
    {
        $lados = Lado::where('Lado','LIKE',"%$this->search%")
                    ->orWhere('Significado','LIKE',"%$this->search%")
                    ->orderBy('id','ASC')
                    ->paginate($this->perPage);
        return view('livewire.crud.lados-table', compact('lados'));
    }

    public function clear(){
        $this->search = '';
        $this->page = 1;
        $this->perPage = '5';
    }
}
