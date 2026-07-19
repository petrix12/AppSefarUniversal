<?php

namespace App\Livewire\Profile\Sales\Concerns;

trait AuthorizesSalesProfile
{
    protected function authorizeSalesProfile(): void
    {
        abort_unless(auth()->user()?->canViewSalesProfile(), 403);
    }
}
