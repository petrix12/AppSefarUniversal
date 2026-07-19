<?php

namespace App\Livewire\Profile\Sales;

use App\Livewire\Profile\Sales\Concerns\AuthorizesSalesProfile;
use Livewire\Component;

class ChartsPanel extends Component
{
    use AuthorizesSalesProfile;

    public function render()
    {
        $this->authorizeSalesProfile();

        // TODO: preparar datasets (Chart.js/ApexCharts) o usar Livewire charts
        return view('livewire.profile.sales.charts-panel');
    }
}
