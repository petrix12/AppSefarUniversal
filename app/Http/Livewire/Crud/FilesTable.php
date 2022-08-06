<?php

namespace App\Http\Livewire\Crud;

use App\Models\File;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class FilesTable extends Component
{
    use WithPagination;

    protected $queryString = [
        'search' => ['except' => ''],
        'perPage' => ['except' => '20']
    ];

    public $search = '';
    public $perPage = '25';

    public function render()
    {
        $users = User::all();

        if(Auth::user()->hasPermissionTo("administrar.documentos")){
            $files = File::where('file','LIKE',"%$this->search%")
                ->orWhere('location','LIKE',"%$this->search%")
                ->orWhere('tipo','LIKE',"%$this->search%")
                ->orWhere('propietario','LIKE',"%$this->search%")
                ->orWhere('IDCliente','LIKE',"%$this->search%")
                ->orWhere('notas','LIKE',"%$this->search%")
                ->orderBy('updated_at','DESC')
                ->paginate($this->perPage);
        }else{
            $files = File::whereUserId(Auth::id())->latest()
                ->where('file','LIKE',"%$this->search%")
                ->paginate($this->perPage);
        }
        return view('livewire.crud.files-table', compact('files', 'users'));
    }

    public function clear(){
        $this->search = '';
        $this->page = 1;
        $this->perPage = '25';
    }
}
