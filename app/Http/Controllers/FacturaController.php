<?php

namespace App\Http\Controllers;

use App\Models\Factura;
use App\Models\Compras;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Stripe;

class FacturaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $query = 'SELECT a.*, b.name, b.email, b.passport FROM facturas as a, users as b WHERE a.id_cliente = b.id ORDER BY a.created_at DESC;';

        $datos_factura = json_decode(json_encode(DB::select(DB::raw($query))),true);

        return view('crud.comprobantes.index', compact('datos_factura'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function viewcomprobante(Request $request)
    {
        $query = "SELECT a.*, b.name, b.passport, b.email, b.phone, b.created_at as fecha_de_registro FROM facturas as a, users as b WHERE a.id_cliente = b.id AND a.id='$request->id';";
        $datos_factura = json_decode(json_encode(DB::select(DB::raw($query))),true);

        $productos = json_decode(json_encode(Compras::where("hash_factura", $datos_factura[0]["hash_factura"])->get()),true);

        $pdf = PDF::loadView('crud.comprobantes.pdfintel', compact('datos_factura', 'productos'));

        return $pdf->stream("comprobantecliente.pdf", array("Attachment" => false));
    }
}
