<?php

namespace App\Livewire\Profile\Sales;

use App\Livewire\Profile\Sales\Concerns\AuthorizesSalesProfile;
use Livewire\Component;
use App\Models\Task;
use Carbon\Carbon;

class PendingTasks extends Component
{
    use AuthorizesSalesProfile;

    public function render()
    {
        $this->authorizeSalesProfile();

        $today = Carbon::today();

        $tasks = Task::query()
            ->with('contact')
            ->forUser(auth()->id())
            ->open()
            ->orderByRaw("
                CASE
                    WHEN due_date < ? THEN 0
                    WHEN due_date = ? THEN 1
                    ELSE 2
                END
            ", [$today->toDateString(), $today->toDateString()])
            ->orderBy('due_date')
            ->limit(10)
            ->get();

        return view('livewire.profile.sales.pending-tasks', compact('tasks'));
    }
}
