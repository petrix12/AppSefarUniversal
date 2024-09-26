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

class SendMonthReportEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'send:monthreport';

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

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $peticion = [];

        $fechaActual = Carbon::now()->setTimezone('UTC');

        $fechaInicio = Carbon::now()->subMonth()->startOfMonth()->setTime(0, 0, 0)->setTimezone('UTC');

        $fechaFin = Carbon::now()->subMonth()->endOfMonth()->setTime(23, 59, 59)->setTimezone('UTC');

        Carbon::setLocale('es');
        $nombreMes = ucfirst($fechaInicio->translatedFormat('F Y'));

        $peticion['dia'] = $fechaInicio->day;
        $peticion['mes'] = $fechaInicio->month;
        $peticion['año'] = $fechaInicio->year;

        $usuariosHoy = User::with('compras')
            ->whereBetween('created_at', [$fechaInicio, $fechaFin])
            ->get();

        $facturas = json_decode(
            json_encode(
                Factura::where('met', 'stripe')
                    ->whereHas('compras', function($query) {
                        $query->where('pagado', 1);
                    })
                    ->whereBetween('created_at', [$fechaInicio, $fechaFin])
                    ->with(['compras' => function($query) {
                        $query->where('pagado', 1)
                                ->select('servicio_hs_id', 'monto', 'hash_factura');
                    }])
                    ->get()
                    ->flatMap(function($factura) {
                        return $factura->compras;
                    })
                    ->groupBy('servicio_hs_id')
                    ->map(function($compras) {
                        return $compras->sum('monto');
                    })
            ),
            true
        );

        // Usuarios registrados en los últimos 30 días
        $usuariosUltimos30Dias = User::where('created_at', '>=', $fechaActual->subDays(30))->get();

        // Número de personas registradas hoy
        $registrosHoy = $usuariosHoy->count();

        // Promedio de registros en el mes actual
        $promedioMesActual = User::whereMonth('created_at', $peticion['mes'])
                                ->whereYear('created_at', $peticion['año'])
                                ->count() / $fechaActual->daysInMonth;

        $diaMasRegistrosMesActual = User::whereMonth('created_at', $peticion['mes'])
                                ->whereYear('created_at', $peticion['año'])
                                ->selectRaw('DAY(created_at) as dia, COUNT(*) as registros')
                                ->groupBy('dia')
                                ->orderBy('registros', 'desc')
                                ->first();

        $diaMenosRegistrosMesActual = User::whereMonth('created_at', $peticion['mes'])
                                    ->whereYear('created_at', $peticion['año'])
                                    ->selectRaw('DAY(created_at) as dia, COUNT(*) as registros')
                                    ->groupBy('dia')
                                    ->orderBy('registros', 'asc')
                                    ->first();


        // Promedio de registros en el mes anterior
        $mesAnterior = $fechaActual->subMonth();
        $promedioMesAnterior = User::whereMonth('created_at', $mesAnterior->month)
                                    ->whereYear('created_at', $mesAnterior->year)
                                    ->count() / $mesAnterior->daysInMonth;

        $diaMasRegistrosMesAnterior = User::whereMonth('created_at', $mesAnterior->month)
                                ->whereYear('created_at', $mesAnterior->year)
                                ->selectRaw('DAY(created_at) as dia, COUNT(*) as registros')
                                ->groupBy('dia')
                                ->orderBy('registros', 'desc')
                                ->first();

        $diaMenosRegistrosMesAnterior = User::whereMonth('created_at', $mesAnterior->month)
                                    ->whereYear('created_at', $mesAnterior->year)
                                    ->selectRaw('DAY(created_at) as dia, COUNT(*) as registros')
                                    ->groupBy('dia')
                                    ->orderBy('registros', 'asc')
                                    ->first();

        // Promedio de registros en el mismo mes del año anterior
        $añoAnterior = $fechaActual->subYear();
        $promedioMismoMesAñoAnterior = User::whereMonth('created_at', $peticion['mes'])
                                            ->whereYear('created_at', $añoAnterior->year)
                                            ->count() / $fechaActual->daysInMonth;

        $diaMasRegistrosMesActual_aa = User::whereMonth('created_at', $peticion['mes'])
                                ->whereYear('created_at', $añoAnterior->year)
                                ->selectRaw('DAY(created_at) as dia, COUNT(*) as registros')
                                ->groupBy('dia')
                                ->orderBy('registros', 'desc')
                                ->first();

        $diaMenosRegistrosMesActual_aa = User::whereMonth('created_at', $peticion['mes'])
                                ->whereYear('created_at', $añoAnterior->year)
                                ->selectRaw('DAY(created_at) as dia, COUNT(*) as registros')
                                ->groupBy('dia')
                                ->orderBy('registros', 'asc')
                                ->first();

        // Promedio de registros en el mes anterior al mes actual del año anterior
        $mesAnteriorAñoAnterior = $añoAnterior->subMonth();
        $promedioMesAnteriorAñoAnterior = User::whereMonth('created_at', $mesAnteriorAñoAnterior->month)
                                                ->whereYear('created_at', $mesAnteriorAñoAnterior->year)
                                                ->count() / $mesAnteriorAñoAnterior->daysInMonth;

        $diaMasRegistrosMesAnterior_aa = User::whereMonth('created_at', $mesAnteriorAñoAnterior->month)
                                ->whereYear('created_at', $mesAnteriorAñoAnterior->year)
                                ->selectRaw('DAY(created_at) as dia, COUNT(*) as registros')
                                ->groupBy('dia')
                                ->orderBy('registros', 'desc')
                                ->first();

        $diaMenosRegistrosMesAnterior_aa = User::whereMonth('created_at', $mesAnteriorAñoAnterior->month)
                                ->whereYear('created_at', $mesAnteriorAñoAnterior->year)
                                ->selectRaw('DAY(created_at) as dia, COUNT(*) as registros')
                                ->groupBy('dia')
                                ->orderBy('registros', 'asc')
                                ->first();

        $fechaActual = Carbon::create($peticion['año'], $peticion['mes'], $peticion['dia']);

        $lastMonth = $fechaActual->copy()->subDays(40);
        $fechaSiguiente = $fechaActual->copy()->addDay();

        $lastMonthStr = $lastMonth->format('Y-m-d');
        $fechaSiguienteStr = $fechaSiguiente->format('Y-m-d');

        $datesInRange = [];
        $currentDate = $lastMonth->copy();
        while ($currentDate < $fechaSiguiente) {
            $datesInRange[$currentDate->format('Y-m-d')] = 0; // Inicializa con 0 registros
            $currentDate->addDay();
        }

        // Ejecuta la consulta
        $registrations = DB::select(
            DB::raw("
                SELECT DATE(created_at) as date, COUNT(*) as count
                FROM users
                WHERE created_at >= :lastMonth AND created_at < :fechaSiguiente
                GROUP BY DATE(created_at)
                ORDER BY date ASC
            "),
            [
                'lastMonth' => $lastMonthStr,
                'fechaSiguiente' => $fechaSiguienteStr
            ]
        );

        // Asigna los registros obtenidos a las fechas correspondientes
        foreach ($registrations as $registration) {
            $date = $registration->date;
            $count = $registration->count;
            $datesInRange[$date] = $count;
        }

        // Convierte el resultado a un array de objetos para una respuesta consistente
        $last30Registrations = [];
        foreach ($datesInRange as $date => $count) {
            $last30Registrations[] = (object)[
                'date' => $date,
                'count' => $count
            ];
        }

        $last30Registrations = array_slice($registrations, -30);



        $datosgraficos = [
            'mes_actual' => [
                'promedio' => round($promedioMesActual, 2),
                'maximo' => $diaMasRegistrosMesActual->registros,
                'minimo' => $diaMenosRegistrosMesActual->registros,
                'total' => User::whereMonth('created_at', $peticion['mes'])
                        ->whereYear('created_at', $peticion['año'])
                        ->count()
            ],
            'mes_anterior' => [
                'promedio' => round($promedioMesAnterior, 2),
                'maximo' => $diaMasRegistrosMesAnterior->registros,
                'minimo' => $diaMenosRegistrosMesAnterior->registros,
                'total' => User::whereMonth('created_at', $peticion['mes'] == 1 ? 12 : $peticion['mes'] - 1)
                        ->whereYear('created_at', $peticion['año'])
                        ->count()
            ],
            'mes_actual_aa' => [
                'promedio' => round($promedioMismoMesAñoAnterior, 2),
                'maximo' => $diaMasRegistrosMesActual_aa->registros,
                'minimo' => $diaMenosRegistrosMesActual_aa->registros,
                'total' => User::whereMonth('created_at', $peticion['mes'])
                        ->whereYear('created_at', $añoAnterior->year)
                        ->count()
            ],
            'mes_anterior_aa' => [
                'promedio' => round($promedioMesAnteriorAñoAnterior, 2),
                'maximo' => $diaMasRegistrosMesAnterior_aa->registros,
                'minimo' => $diaMenosRegistrosMesAnterior_aa->registros,
                'total' => User::whereMonth('created_at', $peticion['mes'] == 1 ? 12 : $peticion['mes'] - 1)
                        ->whereYear('created_at', $peticion['año']-1)
                        ->count()
            ]
        ];

        $datosgraficosporcentaje = [
            'mes_actual' => [
                'promedio' => round($promedioMesActual*100/$diaMasRegistrosMesActual->registros, 2),
                'maximo' => 100,
                'minimo' => 0,
            ],
            'mes_anterior' => [
                'promedio' => round($promedioMesAnterior*100/$diaMasRegistrosMesAnterior->registros, 2),
                'maximo' => 100,
                'minimo' => 0,
            ],
            'mes_actual_aa' => [
                'promedio' => round($promedioMismoMesAñoAnterior*100/$diaMasRegistrosMesActual_aa->registros, 2),
                'maximo' => 100,
                'minimo' => 0,
            ],
            'mes_anterior_aa' => [
                'promedio' => round($promedioMesAnteriorAñoAnterior*100/$diaMasRegistrosMesAnterior_aa->registros, 2),
                'maximo' => 100,
                'minimo' => 0,
            ]
        ];

        $labels = [];
        $data = [];
        foreach ($last30Registrations as $registration) {
            $labels[] = $registration->date;
            $data[] = $registration->count;
        }

        $chartConfig = [
            'type' => 'line',
            'data' => [
                'labels' => $labels,
                'datasets' => [[
                    'label' => 'Registros',
                    'data' => $data,
                    'fill' => false,
                    'borderColor' => 'rgba(0, 0, 0, 0.5)',
                    'backgroundColor' => '#093143'
                ]]
            ],
            'options' => [
                'title' => [
                    'display' => true,
                    'text' => 'Registros de Usuarios en los Últimos 30 Días'
                ],
                'scales' => [
                    'xAxes' => [[
                        'scaleLabel' => [
                            'display' => true,
                            'labelString' => 'Fecha'
                        ]
                    ]],
                    'yAxes' => [[
                        'scaleLabel' => [
                            'display' => true,
                            'labelString' => 'Cantidad de Registros'
                        ]
                    ]]
                ]
            ]
        ];

        $chartConfignight = [
            'type' => 'line',
            'data' => [
                'labels' => $labels,
                'datasets' => [[
                    'label' => 'Registros',
                    'data' => $data,
                    'fill' => false,
                    'borderColor' => 'rgba(255, 255, 255, 0.5)', // Color del borde
                    'backgroundColor' => '#093143',
                    'color' => '#eeeeee'
                ]]
            ],
            'options' => [
                'legend' => [
                    'labels' => [
                        'fontColor' => '#eeeeee' // Color del texto de la leyenda
                    ]
                ],
                'scales' => [
                    'xAxes' => [[
                        'scaleLabel' => [
                            'display' => true,
                            'labelString' => 'Fecha',
                            'fontColor' => '#eeeeee' // Color del texto del eje X
                        ],
                        'ticks' => [
                            'fontColor' => '#eeeeee' // Color del texto de las etiquetas del eje X
                        ]
                    ]],
                    'yAxes' => [[
                        'scaleLabel' => [
                            'display' => true,
                            'labelString' => 'Cantidad de Registros',
                            'fontColor' => '#eeeeee' // Color del texto del eje Y
                        ],
                        'ticks' => [
                            'fontColor' => '#eeeeee' // Color del texto de las etiquetas del eje Y
                        ]
                    ]]
                ]
            ]
        ];

        $chartUrl = mostrarGraficoQuickChartMonth('https://quickchart.io/chart?c=' . urlencode(json_encode($chartConfig)));
        $chartNight = 'https://quickchart.io/chart?c=' . urlencode(json_encode($chartConfignight));

        $usuariosPorServicio = [];

        foreach ($usuariosHoy as $usuario) {
            $servicioHsIds = $usuario->compras->pluck('servicio_hs_id')->join(', ');

            if ($servicioHsIds) {
                foreach (explode(', ', $servicioHsIds) as $servicio) {
                    if (!isset($usuariosPorServicio[$servicio])) {
                        $usuariosPorServicio[$servicio] = 0;
                    }
                    $usuariosPorServicio[$servicio]++;
                }
            } else {
                $servicio = $usuario->servicio;
                if (!isset($usuariosPorServicio[$servicio])) {
                    $usuariosPorServicio[$servicio] = 0;
                }
                $usuariosPorServicio[$servicio]++;
            }
        }

        $fechaInicioFormato = Carbon::parse($fechaInicio)->format('d/m/Y');
        $fechaFinFormato = Carbon::parse($fechaFin)->format('d/m/Y');

        $pdf = PDF::loadView('reportes.plantillames', compact(
            'peticion',
            'usuariosHoy',
            'usuariosUltimos30Dias',
            'registrosHoy',
            'promedioMesActual',
            'promedioMesAnterior',
            'promedioMismoMesAñoAnterior',
            'promedioMesAnteriorAñoAnterior',
            'datosgraficos',
            'datosgraficosporcentaje',
            'registrations',
            'chartUrl',
            'chartNight',
            'usuariosPorServicio',
            'fechaInicio',
            'fechaFin',
            'fechaInicioFormato',
            'fechaFinFormato',
            'nombreMes',
            'facturas'
        ));
        $pdfContent = $pdf->output();

        Mail::send('mail.reporte-monthly', compact(
            'peticion',
            'usuariosHoy',
            'usuariosUltimos30Dias',
            'registrosHoy',
            'promedioMesActual',
            'promedioMesAnterior',
            'promedioMismoMesAñoAnterior',
            'promedioMesAnteriorAñoAnterior',
            'datosgraficos',
            'datosgraficosporcentaje',
            'registrations',
            'chartUrl',
            'chartNight',
            'usuariosPorServicio',
            'fechaInicio',
            'fechaFin',
            'nombreMes'
        ), function ($message) use ($pdfContent, $peticion, $fechaInicio, $fechaFin, $nombreMes) {
            $message->to([
                'dpm.ladera@sefarvzla.com',
                'sistemasccs@sefarvzla.com',
                'crisantoantonio@gmail.com',
                'gflorez@sefarvzla.com',
                'practicanteit@sefarvzla.com'
                ])
                    ->subject('Reporte Mensual - ' . $nombreMes)
                    ->attachData($pdfContent, 'reporte_mensual_' . $nombreMes . '.pdf', [
                        'mime' => 'application/pdf',
                    ]);
        });

        $this->info('Reporte diario generado y enviado con éxito.');
    }
}


function mostrarGraficoQuickChartMonth($chartUrl, $mimeType = 'image/png') {
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
