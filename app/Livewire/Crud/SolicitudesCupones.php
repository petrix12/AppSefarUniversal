<?php

namespace App\Livewire\Crud;

use Livewire\Component;
use App\Models\SolicitudCupon;
use Livewire\WithPagination;
use App\Models\Coupon;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

use App\Mail\CuponAprobadoMailable;
use App\Mail\CuponRechazadoMailable;
use Illuminate\Support\Facades\Mail;

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
        $cuponsol = SolicitudCupon::find($id);

        if ($cuponsol->estatus_cupon == 1) {
            // Si el cupón ya está aprobado, no hacer nada
            return;
        }

        // Aprobar cupón
        $cuponsol->estatus_cupon = 1; // Cambia estatus a aprobado
        $cuponsol->aprobado = 1; // Aprobado
        $cuponsol->save();

        // Generar la fecha de vencimiento
        $date = Carbon::now()->addDays(30)->format('Y-m-d');
        // Generar código único
        $couponCode = $this->generateUniqueCouponCode();

        try {
            // Crear el cupón
            Coupon::create([
                'couponcode' => $couponCode,
                'percentage' => $cuponsol->porcentaje_descuento,
                'expire' => $date,
                'name' => "Aprobado por Administracion",
                'solicitante' => $cuponsol->nombre_solicitante . " " . $cuponsol->apellidos_solicitante . " (" . $cuponsol->correo_solicitante . ")",
                'cliente' => $cuponsol->nombre_cliente . " " . $cuponsol->apellidos_cliente . " (" . $cuponsol->correo_cliente . ")",
                'motivo' => $cuponsol->motivo_solicitud,
                'enabled' => 1
            ]);

            // Definir destinatarios según el tipo de cupón
            $destinatarios = [];
            if ($cuponsol->tipo_cupon == "Cupones de registro 100% - Gratuitos por pagos en efectivo o transferencia.") {
                $destinatarios = ['admin.sefar@sefarvzla.com', $cuponsol->correo_solicitante];
            } else {
                $destinatarios = ['veronica.poletto@sefarvzla.com', 'yeinsondiaz@sefarvzla.com', $cuponsol->correo_solicitante];
            }

            // Enviar el correo de confirmación de aprobación
            Mail::to($destinatarios)->send(new CuponAprobadoMailable(
                $couponCode,
                $cuponsol->nombre_cliente . " " . $cuponsol->apellidos_cliente,
                $cuponsol->nombre_solicitante . " " . $cuponsol->apellidos_solicitante,
                $date
            ));

            session()->flash('message', 'Cupón aprobado exitosamente.');
        } catch (\Illuminate\Database\QueryException $ex) {
            Alert::error('Error', 'El cupón ya existe');
        }
    }

    public function reject($id)
    {
        $cuponsol = SolicitudCupon::find($id);

        // Si el cupón ya está aprobado o rechazado, no hacer nada
        if ($cuponsol->estatus_cupon == 1) {
            return;
        }

        // Rechazar cupón
        $cuponsol->estatus_cupon = 1; // Cambia estatus a rechazado
        $cuponsol->aprobado = 0; // Rechazado
        $cuponsol->save();

        // Definir destinatarios según el tipo de cupón
        $destinatarios = [];
        if ($cuponsol->tipo_cupon == "Cupones de registro 100% - Gratuitos por pagos en efectivo o transferencia.") {
            $destinatarios = ['admin.sefar@sefarvzla.com', $cuponsol->correo_solicitante];
        } else {
            $destinatarios = ['veronica.poletto@sefarvzla.com', 'yeinsondiaz@sefarvzla.com', $cuponsol->correo_solicitante];
        }

        // Enviar el correo de confirmación de rechazo
        Mail::to($destinatarios)->send(new CuponRechazadoMailable(
            $cuponsol->nombre_cliente . " " . $cuponsol->apellidos_cliente,
            $cuponsol->nombre_solicitante . " " . $cuponsol->apellidos_solicitante
        ));

        session()->flash('message', 'Cupón rechazado exitosamente.');
    }

    // Función para generar código de cupón único
    private function generateUniqueCouponCode()
    {
        do {
            $couponCode = strtoupper(substr(str_shuffle("ABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 4)) .
                        substr(str_shuffle("0123456789"), 0, 4);
        } while (DB::table('coupons')->where('couponcode', $couponCode)->exists());

        return $couponCode;
    }

    public function updatingSearch()
    {
        // Reiniciar la paginación cuando se cambie la búsqueda
        $this->resetPage();
    }
}
