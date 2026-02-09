<?php

namespace App\Livewire\Profile\Sales;

use Livewire\Component;
use App\Models\User;
use App\Models\Document;

class Kpis extends Component
{
    public function render()
    {
        $user = auth()->user();

        // ✅ Clientes asignados por owner_id (igual que tu UsersTable)
        $clientes = User::where('owner_id', $user->id)->count();

        // ✅ Documentos visibles para este user (si quieres “documentos compartidos / librería”)
        // Ajusta roles/visibilidad a tu lógica real:
        $docsQuery = Document::query();

        if ($user->hasRole('Coord. Ventas')) {
            $docsQuery->whereIn('visibility', ['coordventas', 'todos']);
        } elseif ($user->hasRole('Proveedor')) {
            $docsQuery->whereIn('visibility', ['proveedores', 'todos']);
        } else {
            $docsQuery->where('visibility', 'todos');
        }

        $docs = $docsQuery->count();

        return view('livewire.profile.sales.kpis', [
            'clientes'  => $clientes,
            'docs'      => $docs,
            'leads'     => 0,
            'ventasMes' => 0,
        ]);
    }
}
