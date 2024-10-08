<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SolicitudCupon;

class SolicitudCuponController extends Controller
{
    public function index(){
        return view('crud.solicitudescupones.index');
    }

    public function aprobarcupon($id){
        $cuponsol = SolicitudCupon::find($id);

        dd($cuponsol);
    }

    public function rechazarcupon($id){
        $cuponsol = SolicitudCupon::find($id);

        dd($cuponsol);
    }
}
