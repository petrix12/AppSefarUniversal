<?php

namespace App\Http\Controllers;

use App\Models\HsReferido;
use Illuminate\Http\Request;

class HsReferidoController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $hsreferidos = HsReferido::get();
        return view('crud.hsreferidos.index', compact('hsreferidos'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\HsReferido  $hsReferido
     * @return \Illuminate\Http\Response
     */
    public function show(HsReferido $hsReferido)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\HsReferido  $hsReferido
     * @return \Illuminate\Http\Response
     */
    public function edit(HsReferido $hsReferido)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\HsReferido  $hsReferido
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, HsReferido $hsReferido)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\HsReferido  $hsReferido
     * @return \Illuminate\Http\Response
     */
    public function destroy(HsReferido $hsReferido)
    {
        //
    }
}
