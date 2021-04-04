<?php

namespace App\Http\Livewire\Crud;

use App\Models\TFile;
use Livewire\Component;
use Livewire\WithPagination;

class TFilesTable extends Component
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
        $t_files = TFile::where('tipo','LIKE',"%$this->search%")
                ->orWhere('notas','LIKE',"%$this->search%")
                ->orderBy('tipo','ASC')
                ->paginate($this->perPage);
        return view('livewire.crud.t-files-table', compact('t_files'));
    }

    public function clear(){
        $this->search = '';
        $this->page = 1;
        $this->perPage = '10';
    }
}
