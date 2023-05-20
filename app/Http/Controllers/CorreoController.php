<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Mail\RegistroCliente;
use Illuminate\Support\Facades\Mail;

class CorreoController extends Controller
{
    public function testcorreos(){
        return view('pruebas.correos');
    }

    public function sendcorreo(Request $request){
        $mail_cliente = new RegistroCliente(auth()->user());
        $result = Mail::to($request->email)->send($mail_cliente);

        dd($result);
    }
}
