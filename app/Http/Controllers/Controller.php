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

        if(Auth::user()->hasRole('Cliente')){
            $IDCliente = Auth::user()->passport;
            return view('arboles.tree', compact('IDCliente'));
        }

        $countries = Country::where('pais','!=','aanull')
                        ->orderBy('pais','ASC')->get();
        $user = Auth()->user();
        
        return view('inicio', compact('countries', 'user'));
    }
}
