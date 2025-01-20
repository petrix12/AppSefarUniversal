<?php

namespace App\Livewire\Crud;

use App\Models\Parentesco;
use Livewire\WithPagination;
use Livewire\Component;

class ParentescosTable extends Component
{
    use WithPagination;

    protected $queryString = [
        'search' => ['except' => ''],
        'perPage' => ['except' => '10']
    ];

    public $search = '';
    public $perPage = '10';
    public $page = '1';

    public function render()
    {
        $parentescos = Parentesco::where('Parentesco','LIKE',"%$this->search%")
                        ->orWhere('Inverso','LIKE',"%$this->search%")
                        ->orderBy('Parentesco','ASC')
                        ->paginate($this->perPage);
        return view('livewire.crud.parentescos-table', compact('parentescos'));
    }

    public function clear(){
        $this->search = '';
        $this->page = 1;
        $this->perPage = '10';
    }
}
