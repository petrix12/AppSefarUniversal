<?php

namespace App\Livewire\Crud;

use App\Models\Format;
use Livewire\WithPagination;
use Livewire\Component;

class FormatsTable extends Component
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
        return view('livewire.crud.formats-table', [
            'formats' => Format::where('formato','LIKE',"%$this->search%")
                ->orWhere('ubicacion','LIKE',"%$this->search%")
                ->orderBy('formato','ASC')
                ->paginate($this->perPage)
        ]);
    }

    public function clear(){
        $this->search = '';
        $this->page = 1;
        $this->perPage = '10';
    }
}
