<?php

namespace App\Http\Livewire\Crud;

use App\Models\Coupon;
use Illuminate\Support\Facades\DB;
use Livewire\WithPagination;
use Livewire\Component;

class CouponsTable extends Component
{
    use WithPagination;

    protected $queryString = [
        'search' => ['except' => ''],
        'perPage' => ['except' => '15']
    ];

    public $search = '';
    public $perPage = '15';
    public $asc = 'DESC';
    
    public function render()
    {
        //$rol = Auth()->user()->hasRole('Traviesoevans');
        $coupons = Coupon::where('couponcode','LIKE',"%$this->search%")
                ->orWhere('percentage','LIKE',"%$this->search%")
                ->orWhere('expire','LIKE',"%$this->search%")
                ->paginate($this->perPage);
       
        return view('livewire.crud.coupons-table', compact('coupons'));
    }

    public function clear(){
        $this->search = '';
        $this->page = 1;
        $this->perPage = '15';
    }

    public function limpiar_page(){
        $this->reset('page');
    }

    public function forma_ordenar(){
        $this->asc == 'ASC' ? $this->asc = 'DESC' : $this->asc = 'ASC';
    }
}
