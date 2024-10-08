<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SolicitudCupon;
use App\Models\Coupon;
use Illuminate\Support\Facades\DB;

use Carbon\Carbon;

use App\Mail\CuponAprobadoMailable;
use App\Mail\CuponRechazadoMailable;
use Illuminate\Support\Facades\Mail;

class SolicitudCuponController extends Controller
{
    public function index(){
        return view('crud.solicitudescupones.index');
    }

    public function aprobarcupon($id){
        $cuponsol = SolicitudCupon::find($id);

        if ($cuponsol->estatus_cupon == 1) {
            return;
        }

        $cuponsol->estatus_cupon = 1;
        $cuponsol->aprobado = 1;
        $cuponsol->save();

        $date = Carbon::now()->addDays(30)->format('Y-m-d');

        $couponCode = $this->generateUniqueCouponCode();

        try {
            Coupon::create([
                'couponcode' => $couponCode,
                'percentage' => $cuponsol->porcentaje_descuento,
                'expire' => $date,
                'name' => "Aprobado por Administracion",
                'solicitante' => $cuponsol->nombre_solicitante." ".$cuponsol->apellidos_solicitante. "(".$cuponsol->correo_solicitante.")",
                'cliente' => $cuponsol->nombre_cliente." ".$cuponsol->apellidos_cliente. "(".$cuponsol->correo_cliente.")",
                'motivo' => $cuponsol->motivo_solicitud,
                'enabled' => 1
            ]);

            $destinatarios = [];
            if ($cuponsol->tipo_cupon == "Cupones de registro 100% - Gratuitos por pagos en efectivo o transferencia.") {
                $destinatarios = ['admin.sefar@sefarvzla.com', $cuponsol->correo_solicitante];
            } else {
                $destinatarios = ['veronica.poletto@sefarvzla.com', 'yeinsondiaz@sefarvzla.com', $cuponsol->correo_solicitante]; // Verónica y Yeinson - Ventas
            }

            Mail::to($destinatarios)->send(new CuponAprobadoMailable(
                $couponCode,
                $cuponsol->nombre_cliente." ".$cuponsol->apellidos_cliente,
                $cuponsol->nombre_solicitante." ".$cuponsol->apellidos_solicitante,
                $date
            ));
        } catch (\Illuminate\Database\QueryException $ex) {
            Alert::error('Error', 'El cupón ya existe');
            return back();
        }
    }

    public function rechazarcupon($id){
        $cuponsol = SolicitudCupon::find($id);
        $cuponsol->estatus_cupon = 1; // Cambia estatus a aprobado o rechazado
        $cuponsol->aprobado = 0; // Rechazado
        $cuponsol->save();

        Mail::to($destinatarios)->send(new CuponRechazadoMailable(
            $cuponsol->nombre_cliente." ".$cuponsol->apellidos_cliente,
            $cuponsol->nombre_solicitante." ".$cuponsol->apellidos_solicitante,
        ));
    }
}

private function generateUniqueCouponCode(){
    do {
        $couponCode = strtoupper(substr(str_shuffle("ABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 4)) .
                      substr(str_shuffle("0123456789"), 0, 4);
    } while (DB::table('coupons')->where('couponcode', $couponCode)->exists());

    return $couponCode;
}
