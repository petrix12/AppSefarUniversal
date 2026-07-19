<?php

namespace App\Livewire\Profile\Sales;

use App\Livewire\Profile\Sales\Concerns\AuthorizesSalesProfile;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class RecentCustomers extends Component
{
    use AuthorizesSalesProfile;

    public function render()
    {
        $this->authorizeSalesProfile();

        $authUser = Auth::user();

        $customers = User::query()
            ->select(['id', 'name', 'nombres', 'apellidos', 'email', 'passport', 'owner_id', 'updated_at'])
            ->where('owner_id', $authUser->id)
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get();

        return view('livewire.profile.sales.recent-customers', compact('customers'));
    }
}
