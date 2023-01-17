<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;
use Illuminate\Database\QueryException;

class CouponController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('crud.coupons.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('crud.coupons.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'couponcode' => 'required',
            'percentage' => 'required|numeric|min:1|max:100'
        ]);

        if($request->expire == ""){
            $date = null;
        } else {
            $date = $request->expire;
        }

        try { 
            Coupon::create([
                'couponcode' => trim($request->couponcode),
                'percentage' => trim($request->percentage),
                'expire' => $date
            ]);
        } catch(\Illuminate\Database\QueryException $ex){ 
            Alert::error('Error', 'El cupón ya existe');
            return back();
        }

        // Mensaje 
        Alert::success('¡Éxito!', 'Se ha añadido el cupón: ' . $request->couponcode);

        // Redireccionar a la vista que invocó este método
        return view('crud.coupons.index');
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Coupon  $coupon
     * @return \Illuminate\Http\Response
     */
    public function show(Coupon $coupon)
    {
        return view('crud.coupons.edit', compact('coupon'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Coupon  $coupon
     * @return \Illuminate\Http\Response
     */
    public function edit(Coupon $coupon)
    {
        return view('crud.coupons.edit', compact('coupon'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Coupon  $coupon
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Coupon $coupon)
    {
        $request->validate([
            'couponcode' => 'required',
            'percentage' => 'required|numeric|min:1|max:100'
        ]);

        if($request->expire == ""){
            $date = null;
        } else {
            $date = $request->expire;
        }

        $coupon->couponcode = trim($request->couponcode);
        $coupon->percentage = trim($request->percentage);
        $coupon->expire = $date;

        try { 
            $coupon->save();
        } catch(\Illuminate\Database\QueryException $ex){ 
            Alert::error('Error', 'El cupón ya existe. No puedes duplicarlo.');
            return back();
        }

        // Mensaje 
        Alert::success('¡Éxito!', 'Se ha actualizado el cupón: ' . $request->couponcode);
        
        // Redireccionar a la vista que invocó este método
        return view('crud.coupons.index');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Coupon  $coupon
     * @return \Illuminate\Http\Response
     */
    public function destroy(Coupon $coupon)
    {
        $titulo = $coupon->couponcode;
        
        $coupon->delete();

        Alert::info('¡Advertencia!', 'Se ha eliminado el cupón: ' . $titulo);
        
        return view('crud.coupons.index');
    }
}
