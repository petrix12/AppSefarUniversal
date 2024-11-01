<?php

namespace App\Http\Controllers;

use App\Models\GeneralCoupon;
use Illuminate\Http\Request;

class GeneralCouponController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $cupones = GeneralCoupon::all();

        return view('crud.generalcoupons.index', compact('cupones'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('crud.generalcoupons.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // Validación de datos
        $request->validate([
            'title' => 'required|string|max:255',
            'start_date' => 'required|date|before_or_equal:end_date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'newdiscount' => 'required|integer',
        ]);

        // Inicializar datos de la alerta
        $data = $request->only(['title', 'newdiscount', 'start_date', 'end_date']);
        $data['title'] = strtoupper(preg_replace('/\s+/', '', $data['title']));

        // Crear la alerta en la base de datos
        GeneralCoupon::create($data);

        // Redireccionar con un mensaje de éxito
        return redirect()->route('generalcoupons.index')->with('success', 'El cupón ha sido registrado exitosamente.');
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\GeneralCoupon  $generalCoupon
     * @return \Illuminate\Http\Response
     */
    public function show(GeneralCoupon $generalCoupon)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\GeneralCoupon  $generalCoupon
     * @return \Illuminate\Http\Response
     */
    public function edit(GeneralCoupon $generalCoupon)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\GeneralCoupon  $generalCoupon
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, GeneralCoupon $generalCoupon)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\GeneralCoupon  $generalCoupon
     * @return \Illuminate\Http\Response
     */
    public function destroy(GeneralCoupon $generalCoupon)
    {
        $generalCoupon->delete();

        // Redireccionar con un mensaje de éxito
        return redirect()->route('generalcoupons.index')->with('success', 'El cupón ha sido eliminado exitosamente.');
    }
}
