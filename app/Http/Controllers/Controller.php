<?php

namespace App\Http\Controllers;

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
            return view('consultas.onidex.index');
        }
        
        return view('inicio');
    }
}
