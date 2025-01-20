<?php

namespace App\Livewire\Crud;

use Livewire\WithPagination;
use App\Models\Country;
use Livewire\Component;

class CountriesTable extends Component
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
        return view('livewire.crud.countries-table', [
            'countries' => Country::where('pais','LIKE',"%$this->search%")
                ->orWhere('store','LIKE',"%$this->search%")
                ->orderBy('pais','ASC')
                ->paginate($this->perPage)
        ]);
        //return view('');
    }

    public function clear(){
        $this->search = '';
        $this->page = 1;
        $this->perPage = '10';
    }
}
