<?php

namespace App\Livewire\Crud;

use Livewire\WithPagination;
use App\Models\User;
use App\Models\Compras;
use Livewire\Component;

class UsersTable extends Component
{
    use WithPagination;

    protected $queryString = [
        'search' => ['except' => ''],
        'perPage' => ['except' => '10']
    ];

    public $search = '';
    public $filterServicio = '';
    public $filterContrato = '';
    public $filterPago = '';
    public $perPage = 10;

    public function render()
    {
        $users = User::query()
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'LIKE', "%{$this->search}%")
                    ->orWhere('email', 'LIKE', "%{$this->search}%")
                    ->orWhere('passport', 'LIKE', "%{$this->search}%");
                });
            })
            ->when($this->filterServicio !== '', function ($query) {
                $query->where(function ($q) {
                    $q->where('servicio', $this->filterServicio)
                    ->orWhereIn('id', function ($sub) {
                        $sub->select('id_user')
                            ->from('compras')
                            ->where('servicio_hs_id', $this->filterServicio);
                    });
                });
            })
            ->when($this->filterContrato !== '', function ($query) {
                $query->where('contrato', $this->filterContrato);
            })
            ->when($this->filterPago !== '', function ($query) {
                $query->where('pay', $this->filterPago);
            })
            ->orderBy('created_at', 'DESC')
            ->paginate($this->perPage);

        // Obtener servicios únicos desde usuarios
        $serviciosDeUsuarios = User::whereNotNull('servicio')->pluck('servicio')->toArray();
        $serviciosDeCompras = Compras::whereNotNull('servicio_hs_id')->pluck('servicio_hs_id')->toArray();

        // Unir y eliminar duplicados
        $listaServicios = collect(array_unique(array_merge($serviciosDeUsuarios, $serviciosDeCompras)))->sort()->values();

        return view('livewire.crud.users-table', [
            'users' => $users,
            'compras' => Compras::all(),
            'listaServicios' => $listaServicios,
        ]);
    }

    public function clear(){
        $this->search = '';
        $this->perPage = '10';
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->filterServicio = '';
        $this->filterContrato = '';
        $this->filterPago = '';
        $this->resetPage();
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }
}
