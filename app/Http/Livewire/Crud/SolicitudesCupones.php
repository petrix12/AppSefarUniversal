<?php

namespace App\Http\Livewire\Crud;

use Livewire\Component;
use App\Models\SolicitudCupon;
use Livewire\WithPagination;

class SolicitudesCupones extends Component
{
    use WithPagination;

    public $cupon_id, $codigo, $descuento, $fecha_expiracion;
    public $isOpen = 0;
    public $filtro = 'por_aprobar'; // Valor por defecto
    public $search = ''; // Campo de búsqueda
    public $perPage = 10; // Paginación

    public function render()
    {
        // Aplicar la búsqueda y los filtros
        $cupones = SolicitudCupon::query()
            ->where(function($query) {
                $query->where('nombre_solicitante', 'like', '%' . $this->search . '%')
                      ->orWhere('apellidos_solicitante', 'like', '%' . $this->search . '%')
                      ->orWhere('correo_solicitante', 'like', '%' . $this->search . '%')
                      ->orWhere('nombre_cliente', 'like', '%' . $this->search . '%')
                      ->orWhere('apellidos_cliente', 'like', '%' . $this->search . '%')
                      ->orWhere('correo_cliente', 'like', '%' . $this->search . '%')
                      ->orWhere('pasaporte_cliente', 'like', '%' . $this->search . '%');
            });

        // Filtrar cupones por el estatus y la aprobación
        if ($this->filtro === 'por_aprobar') {
            $cupones = $cupones->where('estatus_cupon', 0);
        } elseif ($this->filtro === 'aprobados') {
            $cupones = $cupones->where('estatus_cupon', 1)->where('aprobado', 1);
        } elseif ($this->filtro === 'rechazados') {
            $cupones = $cupones->where('estatus_cupon', 1)->where('aprobado', 0);
        }

        return view('livewire.crud.solicitudescupones', [
            'cupones' => $cupones->paginate($this->perPage)
        ]);
    }

    public function setFiltro($filtro)
    {
        $this->filtro = $filtro; // Cambiar el filtro seleccionado
        $this->resetPage(); // Reiniciar la paginación al cambiar de filtro
    }

    public function approve($id)
    {
        $cupon = SolicitudCupon::findOrFail($id);
        $cupon->estatus_cupon = 1; // Cambia estatus a aprobado o rechazado
        $cupon->aprobado = 1; // Aprobado
        $cupon->save();

        session()->flash('message', 'Cupón aprobado exitosamente.');
    }

    // Método para rechazar el cupón
    public function reject($id)
    {
        $cupon = SolicitudCupon::findOrFail($id);
        $cupon->estatus_cupon = 1; // Cambia estatus a aprobado o rechazado
        $cupon->aprobado = 0; // Rechazado
        $cupon->save();

        session()->flash('message', 'Cupón rechazado exitosamente.');
    }

    public function updatingSearch()
    {
        // Reiniciar la paginación cuando se cambie la búsqueda
        $this->resetPage();
    }
}
