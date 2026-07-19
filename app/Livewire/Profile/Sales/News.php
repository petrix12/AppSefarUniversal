<?php

namespace App\Livewire\Profile\Sales;

use App\Livewire\Profile\Sales\Concerns\AuthorizesSalesProfile;
use App\Models\News as NewsModel;
use Livewire\Component;

class News extends Component
{
    use AuthorizesSalesProfile;

    public function render()
    {
        $this->authorizeSalesProfile();

        $news = NewsModel::query()
            ->latest()
            ->take(10)
            ->get();

        return view('livewire.profile.sales.news', compact('news'));
    }
}
