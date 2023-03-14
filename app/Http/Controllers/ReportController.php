<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Webklex\PDFMerger\Facades\PDFMergerFacade as PDFMerger;
use Telegram\Bot\Api;
use Telegram\Bot\FileUpload\InputFile;

class ReportController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $reports = Report::orderBy('created_at', 'desc')->get();
        return view('crud.reports.index', compact('reports'));
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
     * @param  \App\Models\Report  $report
     * @return \Illuminate\Http\Response
     */
    public function show(Report $report)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Report  $report
     * @return \Illuminate\Http\Response
     */
    public function edit(Report $report)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Report  $report
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Report $report)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Report  $report
     * @return \Illuminate\Http\Response
     */
    public function destroy(Report $report)
    {
        //
    }

    public function makeReport() {
        $dayscount = "4";
        $path = "/pdfReportes/";

        $globalcount = json_decode(json_encode(DB::select(DB::raw("SELECT DATE_FORMAT(DATE(`created_at`), '%M') as nombre, DATE_FORMAT(DATE(`created_at`), '%Y') as fecha, COUNT(id) as contador FROM users GROUP BY YEAR(`created_at`), MONTH(`created_at`);"))),true);

        foreach ($globalcount as $key => $value) {
            switch ($value["nombre"]) {
                case 'January':
                    $globalcount[$key]["nombre"] = "Enero";
                    $globalcount[$key]["promediodiario"] = round($value['contador']/31, 2);
                    break;
                case 'February':
                    $globalcount[$key]["nombre"] = "Febrero";
                    if($value["fecha"]%4==0){
                        $globalcount[$key]["promediodiario"] = round($value['contador']/29, 2);
                    } else {
                        $globalcount[$key]["promediodiario"] = round($value['contador']/28, 2);
                    }
                    break;
                case 'March':
                    $globalcount[$key]["nombre"] = "Marzo";
                    $globalcount[$key]["promediodiario"] = round($value['contador']/31, 2);
                    break;
                case 'April':
                    $globalcount[$key]["nombre"] = "Abril";
                    $globalcount[$key]["promediodiario"] = round($value['contador']/30, 2);
                    break;
                case 'May':
                    $globalcount[$key]["nombre"] = "Mayo";
                    $globalcount[$key]["promediodiario"] = round($value['contador']/31, 2);
                    break;
                case 'June':
                    $globalcount[$key]["nombre"] = "Junio";
                    $globalcount[$key]["promediodiario"] = round($value['contador']/30, 2);
                    break;
                case 'July':
                    $globalcount[$key]["nombre"] = "Julio";
                    $globalcount[$key]["promediodiario"] = round($value['contador']/31, 2);
                    break;
                case 'August':
                    $globalcount[$key]["nombre"] = "Agosto";
                    $globalcount[$key]["promediodiario"] = round($value['contador']/31, 2);
                    break;
                case 'September':
                    $globalcount[$key]["nombre"] = "Septiembre";
                    $globalcount[$key]["promediodiario"] = round($value['contador']/30, 2);
                    break;
                case 'October':
                    $globalcount[$key]["nombre"] = "Octubre";
                    $globalcount[$key]["promediodiario"] = round($value['contador']/31, 2);
                    break;
                case 'November':
                    $globalcount[$key]["nombre"] = "Noviembre";
                    $globalcount[$key]["promediodiario"] = round($value['contador']/30, 2);
                    break;
                case 'December':
                    $globalcount[$key]["nombre"] = "Diciembre";
                    $globalcount[$key]["promediodiario"] = round($value['contador']/31, 2);
                    break;
            }
        }

        $yesterday = date('d-m-Y', strtotime("-".$dayscount." days"));

        $titulo = 'Reporte ' . $yesterday;

        $yesterdayquery = date('Y-m-d', strtotime("-".$dayscount." days"));

        $users = DB::select(DB::raw("SELECT a.*, b.nombre as nombre_referido FROM users as a, hs_referidos as b WHERE b.correo=a.referido_por and a.created_at LIKE '%".$yesterdayquery."%' GROUP BY a.passport"));

        $day = date('d');
        $month = date('m');

        $data['usuarios'] = json_decode(json_encode($users),true);
        $data['fechatexto'] = fechaCastellano(date('d-m-Y H:i:s', strtotime("-".$dayscount." days")));
        $data['promedios'] = $globalcount;

        $pdf = Pdf::loadView('reportes.plantilladiario', compact('data'));
        $payload = $pdf->download()->getOriginalContent();

        Storage::disk('local')->put($path.'diario/'.$titulo.'.pdf', $payload);

        $datainsert['tipo'] = 1;
        $datainsert['descripcion'] = $titulo;
        $datainsert['instrucciones'] = 'app/pdfReportes/diario/'.$titulo.'.pdf';

        #Report::create($datainsert);

        sendTelegram($datainsert['instrucciones'], "Reporte Diario - ".$yesterday);

        $todayday = date('D');

        if($todayday == "Mon"){
            $data = [];
            $year = date('Y', strtotime("-".$dayscount." days"));
            $yesterdayw = date('Y-m-d', strtotime("-".$dayscount." days"));
            $owago = date('Y-m-d', strtotime("-7 days"));

            $users = DB::select(DB::raw("SELECT a.*, b.nombre as nombre_referido, b.tipo as tipo_referido FROM users as a, hs_referidos as b WHERE b.correo=a.referido_por and a.created_at>='".$owago."' AND a.created_at<='".$yesterdayw."' GROUP BY a.passport;"));

            $users_ftb = DB::select(DB::raw("SELECT a.*, b.nombre as nombre_referido, b.tipo as tipo_referido FROM users as a, hs_referidos as b WHERE b.correo=a.referido_por and a.created_at>='".$year."-01-01' AND a.created_at<='".$yesterdayw."' GROUP BY a.passport;"));

            $data['usuarios'] = json_decode(json_encode($users),true);
            $data['users_ftb'] = json_decode(json_encode($users_ftb),true);
            $data['fechatexto'] = fechaCastellano(date('d-m-Y H:i:s', strtotime("-".$dayscount." days")));
            $data["semananum"] = date('W', strtotime("-2 days"));

            $pdf = Pdf::loadView('reportes.plantillasemanal', compact('data'));
            $payload = $pdf->download()->getOriginalContent();

            Storage::disk('local')->put($path.'semanal/'.$titulo.'.pdf', $payload);

            $datainsert['tipo'] = 2;
            $datainsert['descripcion'] = $titulo;
            $datainsert['instrucciones'] = 'app/pdfReportes/semanal/'.$titulo.'.pdf';

            #Report::create($datainsert);

            sendTelegram($datainsert['instrucciones'], "Reporte Semanal - ".$yesterday);
        }
    }
}

function fechaCastellano ($fecha) {
    $fecha = substr($fecha, 0, 10);
    $numeroDia = date('d', strtotime($fecha));
    $dia = date('l', strtotime($fecha));
    $mes = date('F', strtotime($fecha));
    $anio = date('Y', strtotime($fecha));
    $dias_ES = array("Lunes", "Martes", "Miércoles", "Jueves", "Viernes", "Sábado", "Domingo");
    $dias_EN = array("Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday");
    $nombredia = str_replace($dias_EN, $dias_ES, $dia);
    $meses_ES = array("Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre");
    $meses_EN = array("January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December");
    $nombreMes = str_replace($meses_EN, $meses_ES, $mes);
    return $nombredia." ".$numeroDia." de ".$nombreMes." de ".$anio;
}

function sendTelegram($filePDF, $nombre){
    $telegram = new Api(env('TOKEN_TELEGRAM'));

    $response = $telegram->sendDocument([
        'chat_id' => "-".env('CHATID_TELEGRAM'), 
        'document' => InputFile::create(storage_path($filePDF)),
        'caption' => $nombre
    ]);

    $messageId = $response->getMessageId();
}

