<?php

namespace App\View\Components;

use Illuminate\View\Component;

class ChatBubble extends Component
{
    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        // Solo renderizar el componente si el usuario está autenticado
        if (auth()->check()) {
            return view('components.chat-bubble');
        }

        return '';
    }
}
