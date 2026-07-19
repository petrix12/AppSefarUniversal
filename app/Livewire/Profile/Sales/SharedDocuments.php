<?php

namespace App\Livewire\Profile\Sales;

use App\Livewire\Profile\Sales\Concerns\AuthorizesSalesProfile;
use Livewire\Component;
use App\Models\Document;

class SharedDocuments extends Component
{
    use AuthorizesSalesProfile;

    public function render()
    {
        $this->authorizeSalesProfile();

        $user = auth()->user();

        $q = Document::query();

        // Ajusta esta lógica a tus roles/visibilidad reales:
        // - Si "Coord. de Nacionalidad y Genealogía" ve coordventas + todos
        if ($user->hasRole('Coord. de Nacionalidad y Genealogía')) {
            $q->whereIn('visibility', ['coordventas', 'todos']);
        }

        // - Si "Proveedor" ve proveedores + todos
        if ($user->hasRole('Proveedor')) {
            $q->whereIn('visibility', ['proveedores', 'todos']);
        }

        // - Si no es ninguno, por defecto "todos" (ajústalo si quieres)
        if (! $user->hasRole('Coord. de Nacionalidad y Genealogía') && ! $user->hasRole('Proveedor')) {
            $q->where('visibility', 'todos');
        }

        $docs = $q->orderedForLibrary()->take(20)->get();

        return view('livewire.profile.sales.shared-documents', compact('docs'));
    }
}
