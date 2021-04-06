<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TreeController extends Controller
{
    public function tree($IDCliente){
        return view('arboles.tree', compact('IDCliente'));
    }
}
