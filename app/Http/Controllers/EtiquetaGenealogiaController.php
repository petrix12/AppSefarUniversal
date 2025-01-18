<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\User;
use App\Models\Servicio;
use App\Models\HsReferido;
use App\Models\Compras;
use App\Models\Factura;
use App\Models\File;
use App\Models\AssocTlHs;
use Monday;

class EtiquetaGenealogiaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
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
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        $datos = $request->all();

        $user = User::find($request->user_id);

        $monday_id = $user->monday_id;

        $boardId = $request->boardId;

        unset($datos['_token'], $datos['boardId'], $datos['user_id']);

        $query = '
        change_multiple_column_values(
            board_id: '.$boardId.',
            item_id: '.$monday_id.',
            column_values: '.json_encode(json_encode($datos)).'
        ) {
            id
        }
        ';

        $updateResult = json_decode(json_encode(Monday::customMutation($query)), true);

        dd($updateResult);

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
