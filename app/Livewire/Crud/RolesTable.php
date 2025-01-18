<?php

namespace App\Livewire\Crud;

use Livewire\WithPagination;
use App\Models\Role;
use Livewire\Component;

class RolesTable extends Component
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
        return view('livewire.crud.roles-table', [
            'roles' => Role::where('name','LIKE',"%$this->search%")
                ->orderBy('updated_at','DESC')
                ->paginate($this->perPage)
        ]);
    }

    public function clear(){
        $this->search = '';
        $this->page = 1;
        $this->perPage = '5';
    }
}
