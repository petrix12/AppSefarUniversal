<?php

namespace App\Http\Controllers;

use App\Mail\CargaCliente;
use App\Mail\CargaSefar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class ClienteController extends Controller
{
    public function tree(){
        $IDCliente = Auth::user()->passport;
        return view('arboles.tree', compact('IDCliente'));
    }

    public function salir(Request $request){
        // Envía un correo al cliente que ha culminado la carga
        $mail_cliente = new CargaCliente(Auth::user());
        Mail::to(Auth::user()->email)->send($mail_cliente);

        // Envía un correo al equipo de Sefar
        $mail_sefar = new CargaSefar(Auth::user());
        Mail::to([
            'pedro.bazo@sefarvzla.com',
            'gerenciait@sefarvzla.com',
            'egonzalez@sefarvzla.com',
            'analisisgenealogico@sefarvzla.com',
            'asistentedeproduccion@sefarvzla.com',
        ])->send($mail_sefar);
        /* Mail::to(['pedro.bazo@gmail.com'])->send($mail_sefar);
        Mail::to(['gerenciait@sefarvzla.com'])->send($mail_sefar);
        Mail::to(['egonzalez@sefarvzla.com'])->send($mail_sefar);
        Mail::to(['analisisgenealogico@sefarvzla.com'])->send($mail_sefar);
        Mail::to(['asistentedeproduccion@sefarvzla.com'])->send($mail_sefar); */
        /* Mail::to(['organizacionrrhh@sefarvzla.com'])->send($mail_sefar); */

        // Realiza logout de la aplicación
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }
}
