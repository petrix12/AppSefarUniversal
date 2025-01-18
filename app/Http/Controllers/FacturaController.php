<?php

namespace App\Http\Controllers;

use App\Models\Factura;
use App\Models\Compras;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Stripe;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Abort;

class FacturaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $datos_factura = json_decode(json_encode(DB::table('facturas as a')
        ->join('users as b', 'a.id_cliente', '=', 'b.id')
        ->select('a.*', 'b.name', 'b.email', 'b.passport')
        ->orderBy('a.created_at', 'desc')
        ->get()),true);

        return view('crud.comprobantes.index', compact('datos_factura'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function viewcomprobante(Request $request)
    {
        $datos_factura = json_decode(json_encode(DB::table('facturas as a')
        ->join('users as b', 'a.id_cliente', '=', 'b.id')
        ->select('a.*', 'b.name', 'b.passport', 'b.email', 'b.phone', 'b.created_at as fecha_de_registro')
        ->where('a.id', $request->id)
        ->get()),true);

        $productos = json_decode(json_encode(Compras::where("hash_factura", $datos_factura[0]["hash_factura"])->get()),true);

        $pdf = PDF::loadView('crud.comprobantes.pdfintel', compact('datos_factura', 'productos'));

        return $pdf->stream("comprobantecliente.pdf", array("Attachment" => false));
    }

    public function viewcomprobantecliente(Request $request)
    {
        $query = "SELECT a.*, b.name, b.passport, b.email, b.phone, b.created_at, a.id_cliente as fecha_de_registro FROM facturas as a, users as b WHERE a.id_cliente = b.id AND a.id='$request->id';";
        $datos_factura = json_decode(json_encode(DB::select($query)),true);

        if (strval(Auth::id()) != strval($datos_factura[0]["id_cliente"])) {
            abort(404);
        }

        $productos = json_decode(json_encode(Compras::where("hash_factura", $datos_factura[0]["hash_factura"])->get()),true);

        $pdf = PDF::loadView('crud.comprobantes.pdfintel', compact('datos_factura', 'productos'));

        return $pdf->stream("comprobantecliente.pdf", array("Attachment" => false));
    }
}
