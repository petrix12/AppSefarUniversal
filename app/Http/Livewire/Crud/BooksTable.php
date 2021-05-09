<?php

namespace App\Http\Livewire\Crud;

use App\Models\Book;
use Livewire\WithPagination;
use Livewire\Component;

class BooksTable extends Component
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
        $books = Book::where('titulo','LIKE',"%$this->search%")
                    ->orWhere('subtitulo','LIKE',"%$this->search%")
                    ->orWhere('autor','LIKE',"%$this->search%")
                    ->orWhere('editorial','LIKE',"%$this->search%")
                    ->orWhere('coleccion','LIKE',"%$this->search%")
                    ->orWhere('edicion','LIKE',"%$this->search%")
                    ->orWhere('paginacion','LIKE',"%$this->search%")
                    ->orWhere('fecha','LIKE',"%$this->search%")
                    ->orWhere('isbn','LIKE',"%$this->search%")
                    ->orWhere('notas','LIKE',"%$this->search%")
                    ->orWhere('claves','LIKE',"%$this->search%")
                    ->orWhere('catalogador','LIKE',"%$this->search%")
                    ->orWhere('enlace','LIKE',"%$this->search%")
                    ->orderBy('updated_at','DESC')
                    ->paginate($this->perPage);
        return view('livewire.crud.books-table', compact('books'));
    }

    public function clear(){
        $this->search = '';
        $this->page = 1;
        $this->perPage = '10';
    }
}
