<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use HubSpot;
use HubSpot\Client\Crm\Deals\Model\AssociationSpec;
use HubSpot\Client\Crm\Associations\Model\BatchInputPublicObjectId;
use HubSpot\Client\Crm\Associations\Model\PublicObjectId;
use HubSpot\Factory;
use HubSpot\Client\Crm\Contacts\ApiException;
use HubSpot\Client\Crm\Contacts\Model\SimplePublicObjectInput;

class CouponController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $coupons = Coupon::orderBy('enabled', 'desc')
                ->orderBy('created_at', 'desc')
                ->get();
        return view('crud.coupons.index', compact('coupons'));
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
            'percentage' => 'required|numeric|min:1|max:100',
            'solicitante' => 'required',
            'cliente' => 'required',
            'motivo' => 'required',
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
                'expire' => $date,
                'name' => auth()->user()->name,
                'solicitante' => trim($request->solicitante),
                'cliente' => trim($request->cliente),
                'motivo' => trim($request->motivo),
                'enabled' => 1
            ]);
        } catch(\Illuminate\Database\QueryException $ex){ 
            Alert::error('Error', 'El cupón ya existe');
            return back();
        }

        // Mensaje 
        Alert::success('¡Éxito!', 'Se ha añadido el cupón: ' . $request->couponcode);

        // Redireccionar a la vista que invocó este método
        $coupons = Coupon::orderBy('enabled', 'desc')
                ->orderBy('created_at', 'desc')
                ->get();
        return view('crud.coupons.index', compact('coupons'));
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
            'percentage' => 'required|numeric|min:1|max:100',
            'solicitante' => 'required',
            'cliente' => 'required',
            'motivo' => 'required',
        ]);

        if($request->expire == ""){
            $date = null;
        } else {
            $date = $request->expire;
        }

        $coupon->couponcode = trim($request->couponcode);
        $coupon->percentage = trim($request->percentage);
        $coupon->expire = $date;
        $coupon->solicitante = trim($request->solicitante);
        $coupon->cliente = trim($request->cliente);
        $coupon->motivo = trim($request->motivo);

        try { 
            $coupon->save();
        } catch(\Illuminate\Database\QueryException $ex){ 
            Alert::error('Error', 'El cupón ya existe. No puedes duplicarlo.');
            return back();
        }

        // Mensaje 
        Alert::success('¡Éxito!', 'Se ha actualizado el cupón: ' . $request->couponcode);
        
        // Redireccionar a la vista que invocó este método
        $coupons = Coupon::orderBy('enabled', 'desc')
                ->orderBy('created_at', 'desc')
                ->get();
        return view('crud.coupons.index', compact('coupons'));
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
        
        $coupons = Coupon::orderBy('enabled', 'desc')
                ->orderBy('created_at', 'desc')
                ->get();
        return view('crud.coupons.index', compact('coupons'));
    }

    public function enable(Request $request)
    {
        $var = $request->all();
        $array = json_decode(json_encode(Coupon::select()->where('id', $var["id"])->get()),true);
        if ( $array[0]["enabled"] == 1 ){
            DB::table('coupons')->where('id', $var["id"])->update(['enabled' => 0]);
        } else {
            DB::table('coupons')->where('id', $var["id"])->update(['enabled' => 1]);
        }
    }

    public function fixCouponHubspot()
    {
        ini_set('max_execution_time', 3000000);
        ini_set('max_input_time', 3000000);

        $hubspot = HubSpot\Factory::createWithAccessToken(env('HUBSPOT_KEY'));
        $query = 'SELECT id, email, pago_cupon, hs_id FROM users where pay>0 and pago_cupon <> "" and pago_cupon is not null and email not like "%sefarvzla.com%" and hs_id is null';

        $globalcount = json_decode(json_encode(DB::select(DB::raw($query))),true);

        foreach ($globalcount as $key => $value) {
            $idcontact = "";

            $filter = new \HubSpot\Client\Crm\Contacts\Model\Filter();
            $filter
                ->setOperator('EQ')
                ->setPropertyName('email')
                ->setValue($value["email"]);

            $filterGroup = new \HubSpot\Client\Crm\Contacts\Model\FilterGroup();
            $filterGroup->setFilters([$filter]);

            $searchRequest = new \HubSpot\Client\Crm\Contacts\Model\PublicObjectSearchRequest();
            $searchRequest->setFilterGroups([$filterGroup]);

            //Llamo a todas las propiedades de Hubspot (Si el dia de mañana hay que añadir algo nuevo, la ventaja que tenemos es que ya me estoy trayendo todos los elementos del arreglo)

            $searchRequest->setProperties([
                "registro_pago",
                "registro_cupon"
            ]);

            //Hago la busqueda del cliente
            $contactHS = $hubspot->crm()->contacts()->searchApi()->doSearch($searchRequest);

            if ($contactHS['total'] != 0){
                $valuehscupon = "";
                //sago solo el id del contacto:
                $idcontact = $contactHS['results'][0]['id'];

                DB::table('users')->where('id', $value['id'])->update(['hs_id' => $idcontact]);

                if (!isset($contactHS['results'][0]['properties']['registro_cupon'])) {
                    $properties1 = [
                        'registro_pago' => '0',
                        'registro_cupon' => $value["pago_cupon"]
                    ];
                    $simplePublicObjectInput = new SimplePublicObjectInput([
                        'properties' => $properties1,
                    ]);
                    
                    $apiResponse = $hubspot->crm()->contacts()->basicApi()->update($idcontact, $simplePublicObjectInput);
                }
            }

            sleep(1);
        }

        print_r($globalcount);
    }
}
