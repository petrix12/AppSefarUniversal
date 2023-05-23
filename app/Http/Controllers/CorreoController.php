<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Mail\RegistroCliente;
use Mail;
use RealRashid\SweetAlert\Facades\Alert;
use Illuminate\Support\Facades\DB;
use App\Models\Compras;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;

class CorreoController extends Controller
{
    public function testcorreos(){
        return view('pruebas.correos');
    }

    public function sendcorreo(Request $request){
        $user = User::findOrFail(auth()->user()->id);
        $pdfContent = createPDF('sef_0GV3gTHPbLhpSi2drxReASxz8znUJLdmFp3gbcdaYDGxdtWDxB');

        /*
        Mail::send('mail.comprobante-mail', ['user' => $user], function ($m) use ($pdfContent, $request) { 
            $m->to($request->email)->subject('SEFAR UNIVERSAL - Hemos procesado su pago satisfactoriamente')->attachData($pdfContent, 'file.pdf', ['mime' => 'application/pdf']);
        });
        */

        Mail::send('mail.comprobante-mail-intel', ['user' => $user], function ($m) use ($pdfContent, $request, $user) { 
            $m->to([
                'pedro.bazo@sefarvzla.com',
                'gerenciait@sefarvzla.com',
                'sistemasccs@sefarvzla.com',
                'automatizacion@sefarvzla.com',
                'sistemascol@sefarvzla.com',
                /* 'egonzalez@sefarvzla.com', */
                'analisisgenealogico@sefarvzla.com',
                'asistentedeproduccion@sefarvzla.com',
                /* 'arosales@sefarvzla.com', */
                /* 'czanella@sefarvzla.com', */
                'organizacionrrhh@sefarvzla.com',
                'gcuriel@sefarvzla.com',
                'organizacionrrhh@sefarvzla.com',
                '20053496@bcc.hubspot.com'
            ])->subject(strtoupper($user->name) . ' (ID: ' . 
                strtoupper($user->passport) . ') HA REALIZADO UN PAGO EN App Sefar Universal')->attachData($pdfContent, 'file.pdf', ['mime' => 'application/pdf']);
        });

        #$result = Mail::to($request->email)->send($mail_cliente);
        #return redirect()->route('testcorreos')->with('Exito', 'Se envió el correo.');
    }
}

function createPDF($dato){
    $query = "SELECT a.*, b.name, b.passport, b.email, b.phone, b.created_at as fecha_de_registro FROM facturas as a, users as b WHERE a.id_cliente = b.id AND a.hash_factura='$dato';";
    $datos_factura = json_decode(json_encode(DB::select(DB::raw($query))),true);

    $productos = json_decode(json_encode(Compras::where("hash_factura", $datos_factura[0]["hash_factura"])->get()),true);

    $pdf = PDF::loadView('crud.comprobantes.pdf', compact('datos_factura', 'productos'));

    return $pdf->output();
}