<?php

namespace App\Livewire\Profile\Sales;

use App\Livewire\Profile\Sales\Concerns\AuthorizesSalesProfile;
use Livewire\Component;

class ProfileHeader extends Component
{
    use AuthorizesSalesProfile;

    public function render()
    {
        $this->authorizeSalesProfile();

        $user = auth()->user();

        // Spatie roles (primer rol si existe)
        $roles = $user->getRoleNames();
        $roleName = $roles->first() ?? 'Sin rol asignado';

        return view('livewire.profile.sales.profile-header', [
            'user' => $user,
            'roleName' => $roleName,
        ]);
    }
}
