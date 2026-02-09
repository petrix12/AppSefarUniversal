<?php

namespace App\Livewire\Profile\Sales;

use Livewire\Component;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class RecentCustomers extends Component
{
    public function render()
    {
        $authUser = Auth::user()->load('roles');
        $rolesIds = $authUser->roles->pluck('id')->toArray();

        $q = User::query()
            ->select(['id','name','nombres','apellidos','email','passport','owner_id','updated_at']);

        // Si es Ventas (15) o Coord Ventas (17): solo sus clientes
        if (!empty(array_intersect($rolesIds, [15, 17]))) {
            $q->where('owner_id', $authUser->id);
        }

        // 100 más recientes por updated_at
        $customers = $q->orderByDesc('updated_at')
                      ->limit(100)
                      ->get();

        return view('livewire.profile.sales.recent-customers', compact('customers'));
    }
}
