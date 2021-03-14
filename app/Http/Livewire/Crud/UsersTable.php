<?php

namespace App\Http\Livewire\Crud;

use Livewire\WithPagination;
use App\Models\User;
use Livewire\Component;

class UsersTable extends Component
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
        return view('livewire.crud.users-table', [
            'users' => User::where('name','LIKE',"%$this->search%")
                ->orWhere('email','LIKE',"%$this->search%")
                ->orWhere('passport','LIKE',"%$this->search%")
                ->orderBy('updated_at','DESC')
                ->paginate($this->perPage)
        ]);
    }

    public function clear(){
        $this->search = '';
        $this->page = 1;
        $this->perPage = '10';
    }
}
