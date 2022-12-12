<?php

namespace App\Http\Controllers;

use App\Models\Country;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;

class GetController extends Controller
{
    public function registro(){
        $countries = Country::all();
        return view('pruebas.registro', compact('countries'));
    }

    public function capturar_parametros_get(Request $request){
        $pos = strpos($request->fullUrl(), '?');
        $parametros = substr($request->fullUrl(), $pos);
        Alert::info('Enlaces para registrar cliente', '
            <small>
                <p><strong>http://sefar.test/register</strong>'.$parametros.'</p>
                <br><hr><br>
                <p><strong>https://app.universalsefar.com/register</strong>'.$parametros.'</p>
                <br><hr><br>
                <p><strong>http://127.0.0.1:8050/register</strong>'.$parametros.'</p>
            </small>'
        )->toHtml()->persistent(true);
        return back();
    }
}
