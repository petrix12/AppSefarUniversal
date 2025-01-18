<?php

namespace App\Livewire\Crud;

use App\Models\Connection;
use Livewire\Component;
use Livewire\WithPagination;

class ConnectionsTable extends Component
{
    use WithPagination;

    protected $queryString = [
        'search' => ['except' => ''],
        'perPage' => ['except' => '5']
    ];

    public $search = '';
    public $perPage = '5';
    
    public function render()
    {
        /* $this->page = 1; */
        $connections = Connection::where('Conexion','LIKE',"%$this->search%")
                    ->orWhere('Significado','LIKE',"%$this->search%")
                    ->orderBy('id','ASC')
                    ->paginate($this->perPage);
        return view('livewire.crud.connections-table', compact('connections'));
    }

    public function clear(){
        $this->search = '';
        $this->page = 1;
        $this->perPage = '5';
    }
}
