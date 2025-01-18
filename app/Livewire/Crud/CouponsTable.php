<?php

namespace App\Livewire\Crud;

use App\Models\Coupon;
use Livewire\WithPagination;
use Livewire\Component;

class CouponsTable extends Component
{
    use WithPagination;

    public $search = '';
    public $perPage = '15';
    
    public function render()
    {
        //$rol = Auth()->user()->hasRole('Traviesoevans');
        $coupons = Coupon::where('couponcode','LIKE',"%$this->search%")
                ->orWhere('percentage','LIKE',"%$this->search%")
                ->orWhere('expire','LIKE',"%$this->search%")
                ->orWhere('name','LIKE',"%$this->search%")
                ->orWhere('solicitante','LIKE',"%$this->search%")
                ->orWhere('cliente','LIKE',"%$this->search%")
                ->orWhere('motivo','LIKE',"%$this->search%")
                ->orderBy('enabled', 'desc')
                ->orderBy('created_at', 'desc')
                ->paginate($this->perPage);
       
        return view('livewire.crud.coupons-table', compact('coupons'));
    }
}
