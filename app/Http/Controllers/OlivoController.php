<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class OlivoController extends Controller
{
    public function olivo($IDCliente){
        return view('arboles.olivo', compact('IDCliente'));
    }
}
