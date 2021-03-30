<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AlberoController extends Controller
{
    public function arbelo($IDCliente){
        return view('arboles.arbelo', compact('IDCliente'));
    }
}
