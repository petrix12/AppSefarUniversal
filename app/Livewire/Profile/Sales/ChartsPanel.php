<?php

namespace App\Livewire\Profile\Sales;

use Livewire\Component;

class ChartsPanel extends Component
{
    public function render()
    {
        // TODO: preparar datasets (Chart.js/ApexCharts) o usar Livewire charts
        return view('livewire.profile.sales.charts-panel');
    }
}
