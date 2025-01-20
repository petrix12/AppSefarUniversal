<?php

namespace App\Livewire\Crud;

use Livewire\WithPagination;
use App\Models\User;
use App\Models\Compras;
use Livewire\Component;

class UsersTable extends Component
{
    use WithPagination;

    protected $queryString = [
        'search' => ['except' => ''],
        'perPage' => ['except' => '10']
    ];

    public $page = '1';
    public $search = '';
    public $perPage = '10';

    public function render()
    {
        return view('livewire.crud.users-table', [
            'users' => User::where('name','LIKE',"%$this->search%")
                ->orWhere('email','LIKE',"%$this->search%")
                ->orWhere('passport','LIKE',"%$this->search%")
                ->orderBy('created_at','DESC')
                ->paginate($this->perPage) ,
            'compras' => Compras::all()
        ]);
    }

    public function clear(){
        $this->search = '';
        $this->page = 1;
        $this->perPage = '10';
    }
}
