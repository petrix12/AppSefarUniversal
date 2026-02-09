<?php

namespace App\Livewire\Profile\Sales;

use Livewire\Component;
use App\Models\Document;

class SharedDocuments extends Component
{
    public function render()
    {
        $user = auth()->user();

        $q = Document::query();

        // Ajusta esta lógica a tus roles/visibilidad reales:
        // - Si "Coord. Ventas" ve coordventas + todos
        if ($user->hasRole('Coord. Ventas')) {
            $q->whereIn('visibility', ['coordventas', 'todos']);
        }

        // - Si "Proveedor" ve proveedores + todos
        if ($user->hasRole('Proveedor')) {
            $q->whereIn('visibility', ['proveedores', 'todos']);
        }

        // - Si no es ninguno, por defecto "todos" (ajústalo si quieres)
        if (! $user->hasRole('Coord. Ventas') && ! $user->hasRole('Proveedor')) {
            $q->where('visibility', 'todos');
        }

        $docs = $q->latest('id')->take(20)->get();

        return view('livewire.profile.sales.shared-documents', compact('docs'));
    }
}
