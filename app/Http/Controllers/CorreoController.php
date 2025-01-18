<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Mail\RegistroCliente;
use App\Mail\CargaCliente;
use Mail;
use RealRashid\SweetAlert\Facades\Alert;
use Illuminate\Support\Facades\DB;
use App\Models\Compras;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;

class CorreoController extends Controller
{
    public function testcorreos(){
        return view('pruebas.correos');
    }

    public function sendcorreo(Request $request){
        $mail_cliente = new CargaCliente(Auth::user());
        \Illuminate\Support\Facades\Mail::to($request->email)->send($mail_cliente);

        #$result = Mail::to($request->email)->send($mail_cliente);
        return redirect()->route('testcorreos')->with('Exito', 'Se envió el correo.');
    }
}

function createPDF($dato){
    $query = "SELECT a.*, b.name, b.passport, b.email, b.phone, b.created_at as fecha_de_registro FROM facturas as a, users as b WHERE a.id_cliente = b.id AND a.hash_factura='$dato';";
    $datos_factura = json_decode(json_encode(DB::select($query)),true);

    $productos = json_decode(json_encode(Compras::where("hash_factura", $datos_factura[0]["hash_factura"])->get()),true);

    $pdf = PDF::loadView('crud.comprobantes.pdf', compact('datos_factura', 'productos'));

    return $pdf->output();
}
