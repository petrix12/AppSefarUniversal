<?php

namespace App\Livewire\Profile\Sales;

use App\Models\News as NewsModel;
use Livewire\Component;

class News extends Component
{
    public function render()
    {
        $news = NewsModel::query()
            ->latest()
            ->take(10)
            ->get();

        return view('livewire.profile.sales.news', compact('news'));
    }
}
