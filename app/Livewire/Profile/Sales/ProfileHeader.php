<?php

namespace App\Livewire\Profile\Sales;

use Livewire\Component;

class ProfileHeader extends Component
{
    public function render()
    {
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
