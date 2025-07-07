<?php

namespace App\Http\Controllers;

use App\Models\Country;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

class Controller extends BaseController
{
    use HasRoles;

    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public function index(){
        if(Auth::user()->hasRole('Administrador')){
            return view('crud.users.index');
        }

        if(Auth::user()->hasRole('Genealogista')){
            return view('crud.agclientes.index');
        }

        if(Auth::user()->hasRole('Produccion')){
            return view('crud.agclientes.index');
        }

        if(Auth::user()->hasRole('Documentalista')){
            return view('crud.miscelaneos.index');
        }

        if(Auth::user()->hasRole('Traviesoevans')){
            return view('crud.agclientes.index');
        }

        if(Auth::user()->hasRole('Vargassequera')){
            return view('crud.agclientes.index');
        }

        if(Auth::user()->hasRole('BadellLaw')){
            return view('crud.agclientes.index');
        }

        if(Auth::user()->hasRole('P&V-Abogados')){
            return view('crud.agclientes.index');
        }

        if(Auth::user()->hasRole('Mujica-Coto')){
            return view('crud.agclientes.index');
        }

        if(Auth::user()->hasRole('German-Fleitas')){
            return view('crud.agclientes.index');
        }

        if(Auth::user()->hasRole('Soma-Consultores')){
            return view('crud.agclientes.index');
        }

        if(Auth::user()->hasRole('MG-Tours')){
            return view('crud.agclientes.index');
        }

        if(Auth::user()->hasRole('Coord. Ventas')){
            return view('crud.users.index');
        }

        // Clientes corrientes
        if (Auth::user()->hasRole("Cliente")){
            if(Auth::user()->pay==0){
                return redirect()->route('clientes.pay');
            } else if (Auth::user()->pay==1 || Auth::user()->pay==3){
                return redirect()->route('clientes.getinfo');
            } else {
                $IDCliente = Auth::user()->passport;
                return redirect('/tree');
            }
        }

        $countries = Country::where('pais','!=','aanull')
                        ->orderBy('pais','ASC')->get();
        $user = Auth()->user();

        return view('inicio', compact('countries', 'user'));
    }
}
