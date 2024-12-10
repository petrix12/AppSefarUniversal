<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PDF;
use Mail;
use App\Models\User;
use App\Models\Factura;
use App\Models\Compras;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Telegram\Bot\Api;
use Telegram\Bot\FileUpload\InputFile;
use Carbon\Carbon;

class SendYearReportEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'send:yearreport';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $anio = date('Y') - 1; // Año pasado
        $anioAnterior = $anio - 1;

        // Definir fechas de inicio y fin para ambos años
        $fechaInicioAnio = Carbon::createFromDate($anio, 1, 1)->startOfDay();
        $fechaFinAnio = Carbon::createFromDate($anio, 12, 31)->endOfDay();
        $fechaInicioAnioAnterior = Carbon::createFromDate($anioAnterior, 1, 1)->startOfDay();
        $fechaFinAnioAnterior = Carbon::createFromDate($anioAnterior, 12, 31)->endOfDay();

        // Usuarios registrados en el año actual
        $usuariosAnioActual = User::whereBetween('created_at', [$fechaInicioAnio, $fechaFinAnio])
            ->where('email', 'not like', '%sefarvzla%')
            ->where('email', 'not like', '%sefaruniversal%')
            ->where('name', 'not like', '%prueba%')
            ->get();

        $usuariosRegistrados = $usuariosAnioActual->count();

        // Usuarios registrados en el año anterior
        $usuariosAnioAnterior = User::whereBetween('created_at', [$fechaInicioAnioAnterior, $fechaFinAnioAnterior])
            ->where('email', 'not like', '%sefarvzla%')
            ->where('email', 'not like', '%sefaruniversal%')
            ->where('name', 'not like', '%prueba%')
            ->get();

        // Calcular promedios de usuarios por día para ambos años
        $promedioAnioActual = $usuariosRegistrados / $fechaInicioAnio->daysInYear;
        $promedioAnioAnterior = $usuariosAnioAnterior->count() / $fechaInicioAnioAnterior->daysInYear;

        // Calcular usuarios registrados por servicio
        $usuariosPorServicioAnioActual = $usuariosAnioActual->groupBy('servicio')->map->count();
        $usuariosPorServicioAnioAnterior = $usuariosAnioAnterior->groupBy('servicio')->map->count();

        // Calcular estatus de los usuarios
        $estatusCount = [
            'No ha pagado' => $usuariosAnioActual->where('pay', 0)->count(),
            'Pagó pero no completó información' => $usuariosAnioActual->where('pay', 1)->count(),
            'Pagó y completó información, pero no firmó contrato' => $usuariosAnioActual->where('pay', 2)->where('contrato', 0)->count(),
            'Pagó, completó información y firmó contrato' => $usuariosAnioActual->where('pay', 2)->where('contrato', 1)->count(),
        ];

        // Generar datos para gráficos de registros por mes
        $labels = [];
        $dataActual = [];
        $dataAnterior = [];

        for ($mes = 1; $mes <= 12; $mes++) {
            $inicioMesActual = Carbon::create($anio, $mes, 1)->startOfDay();
            $finMesActual = $inicioMesActual->copy()->endOfMonth();
            $inicioMesAnterior = Carbon::create($anioAnterior, $mes, 1)->startOfDay();
            $finMesAnterior = $inicioMesAnterior->copy()->endOfMonth();

            $labels[] = $inicioMesActual->translatedFormat('F');
            $dataActual[] = User::whereBetween('created_at', [$inicioMesActual, $finMesActual])
                ->where('email', 'not like', '%sefarvzla%')
                ->where('email', 'not like', '%sefaruniversal%')
                ->where('name', 'not like', '%prueba%')->count();

            $dataAnterior[] = User::whereBetween('created_at', [$inicioMesAnterior, $finMesAnterior])
                ->where('email', 'not like', '%sefarvzla%')
                ->where('email', 'not like', '%sefaruniversal%')
                ->where('name', 'not like', '%prueba%')->count();
        }

        // Configuración para gráficos diurnos y nocturnos
        $chartConfig = [
            'type' => 'line',
            'data' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => "Registros en $anio",
                        'data' => $dataActual,
                        'borderColor' => 'rgba(0, 0, 0, 0.5)',
                        'backgroundColor' => '#093143',
                        'fill' => false,
                    ],
                    [
                        'label' => "Registros en $anioAnterior",
                        'data' => $dataAnterior,
                        'borderColor' => 'rgba(0, 123, 255, 0.5)',
                        'backgroundColor' => '#007bff',
                        'fill' => false,
                    ],
                ],
            ],
        ];

        $chartConfignight = $chartConfig;
        $chartConfignight['data']['datasets'][0]['borderColor'] = 'rgba(255, 255, 255, 0.5)';
        $chartConfignight['data']['datasets'][1]['borderColor'] = 'rgba(123, 255, 255, 0.5)';
        $chartConfignight['options']['scales'] = [
            'xAxes' => [['ticks' => ['fontColor' => '#eeeeee']]],
            'yAxes' => [['ticks' => ['fontColor' => '#eeeeee']]],
        ];

        $chartUrl = mostrarGraficoQuickChartMonth('https://quickchart.io/chart?c=' . urlencode(json_encode($chartConfig)));
        $chartNight = 'https://quickchart.io/chart?c=' . urlencode(json_encode($chartConfignight));

        // Datos para gráficos de barras
        $datosgraficos = [
            'anio_actual' => [
                'promedio' => round($promedioAnioActual, 2),
                'maximo' => max($dataActual),
                'minimo' => min($dataActual),
                'total' => array_sum($dataActual),
            ],
            'anio_anterior' => [
                'promedio' => round($promedioAnioAnterior, 2),
                'maximo' => max($dataAnterior),
                'minimo' => min($dataAnterior),
                'total' => array_sum($dataAnterior),
            ],
        ];

        $datosgraficosporcentaje = [
            'anio_actual' => [
                'promedio' => round(($promedioAnioActual * 100) / max($dataActual), 2),
            ],
            'anio_anterior' => [
                'promedio' => round(($promedioAnioAnterior * 100) / max($dataAnterior), 2),
            ],
        ];

        // Facturas (Stripe)
        $facturas = Factura::where('met', 'stripe')
        ->whereBetween('created_at', [$fechaInicioAnio, $fechaFinAnio])
        ->with(['compras' => function ($query) {
            $query->select('servicio_hs_id', 'monto', 'hash_factura');
        }])
        ->get()
        ->flatMap(function ($factura) {
            return $factura->compras;
        })
        ->groupBy('servicio_hs_id')
        ->map(function ($compras) {
            return $compras->sum('monto');
        })
        ->toArray();

        // Facturas con Cupones
        $facturasCupones = Factura::where('met', 'cupon')
        ->whereBetween('created_at', [$fechaInicioAnio, $fechaFinAnio])
        ->with(['compras' => function ($query) {
            $query->select('servicio_hs_id', 'monto', 'hash_factura');
        }])
        ->get()
        ->flatMap(function ($factura) {
            return $factura->compras;
        })
        ->groupBy('servicio_hs_id')
        ->map(function ($compras) {
            return $compras->sum('monto');
        })
        ->toArray();

        $pdf = PDF::loadView('reportes.plantillaanual', compact(
            'anio',
            'anioAnterior',
            'usuariosAnioActual',
            'usuariosAnioAnterior',
            'usuariosPorServicioAnioActual',
            'usuariosPorServicioAnioAnterior',
            'estatusCount',
            'promedioAnioActual',
            'promedioAnioAnterior',
            'datosgraficos',
            'datosgraficosporcentaje',
            'chartUrl',
            'chartNight',
            'usuariosRegistrados',
            'facturas',
            'facturasCupones'
        ));

        $pdfContent = $pdf->output();

        Mail::send('mail.reporte-yearly', compact(
            'anio',
            'anioAnterior',
            'usuariosAnioActual',
            'usuariosAnioAnterior',
            'usuariosPorServicioAnioActual',
            'usuariosPorServicioAnioAnterior',
            'estatusCount',
            'promedioAnioActual',
            'promedioAnioAnterior',
            'datosgraficos',
            'datosgraficosporcentaje',
            'chartUrl',
            'chartNight',
            'usuariosRegistrados',
            'facturas',
            'facturasCupones'
        ), function ($message) use ($pdfContent, $anio) {
            $message->to([
                'dpm.ladera@sefarvzla.com',
                'sistemasccs@sefarvzla.com',
                'crisantoantonio@gmail.com',
                'gflorez@sefarvzla.com',
                'practicanteit@sefarvzla.com',
                'cguerrero@sefarvzla.com',
                'automatizacion@sefarvzla.com',
                'admin.sefar@sefarvzla.com',
                'yeinsondiaz@sefarvzla.com'
                ])
                    ->subject('Reporte anual - ' . $anio)
                    ->attachData($pdfContent, 'reporte_' . $anio . '.pdf', [
                        'mime' => 'application/pdf',
                    ]);
        });

        $this->info('Reporte anual generado y enviado con éxito.');
    }
}


function mostrarGraficoQuickChartAnual($chartUrl, $mimeType = 'image/png') {
    // Intentar obtener la imagen
    $imageData = file_get_contents($chartUrl);

    // Verificar si la solicitud fue exitosa
    if ($imageData !== false) {
        // Codificar la imagen a base64
        $base64Image = base64_encode($imageData); // Aquí faltaba la codificación
        return 'data:' . $mimeType . ';base64,' . $base64Image;
    } else {
        return false; // O puedes devolver un mensaje de error personalizado
    }
}
