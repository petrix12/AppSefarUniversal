<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\User;
use App\Models\Factura;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
//use Webklex\PDFMerger\Facades\PDFMergerFacade as PDFMerger;
use Telegram\Bot\Api;
use Telegram\Bot\FileUpload\InputFile;
use Carbon\Carbon;

class ReportController extends Controller
{
    public function diarioindex()
    {
        return view('reportes.diario.diario');
    }

    public function mensualindex()
    {
        return view('reportes.mensual.mensual');
    }

    public function anualindex()
    {
        return view('reportes.anual.anual');
    }

    public function semanalindex()
    {
        return view('reportes.semanal.semanal');
    }

    public function getreportediario(Request $request)
    {
        $peticion = $request->all();

        if (isset($peticion["fecha"])){
            $fechaActual = Carbon::create($peticion["fecha"]);
            $peticion['dia'] = $fechaActual->day;
            $peticion['mes'] = $fechaActual->month;
            $peticion['año'] = $fechaActual->year;
        } else {
            $fechaActual = Carbon::create($peticion['año'], $peticion['mes'], $peticion['dia']);
        }

        // Usuarios registrados hoy
        $usuariosHoy = User::with('compras')->whereDate('created_at', $fechaActual)->get();

        $facturas = json_decode(
            json_encode(
                Factura::where('met', 'stripe')
                    ->whereHas('compras', function($query) {
                        $query->where('pagado', 1);
                    })
                    ->whereDate('created_at', $fechaActual)
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
        $usuariosUltimos30Dias = User::where('created_at', '>=', $fechaActual->copy()->subDays(30))->get();

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
        $mesAnterior = $fechaActual->copy()->subMonth();
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
        $añoAnterior = $fechaActual->copy()->subYear();
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

        $chartUrl = 'https://quickchart.io/chart?c=' . urlencode(json_encode($chartConfig));
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

        return view('reportes.diario.reporte', compact(
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
            'facturas'
        ));
    }

    public function getreportesemanal(Request $request)
    {
        $timezone = 'America/Bogota';

        if(isset($request->fecha)){
            $fechaRango = $request->fecha;

            preg_match('/(\d{2}\/\d{2}\/\d{4}) al domingo (\d{2}\/\d{2}\/\d{4})/', $fechaRango, $matches);

            if (count($matches) === 3) {
                $fechaInicioStr = $matches[1];
                $fechaFinStr = $matches[2];

                $fechaInicio = Carbon::createFromFormat('d/m/Y', $fechaInicioStr, $timezone)->startOfDay();

                $fechaFin = Carbon::createFromFormat('d/m/Y', $fechaFinStr, $timezone)->endOfDay();
            }

            $fechaActual = $fechaInicio->copy();

            $peticion['dia'] = $fechaInicio->day;
            $peticion['mes'] = $fechaInicio->month;
            $peticion['año'] = $fechaInicio->year;

        } else {
            // Crear fecha a partir de los valores enviados (día, mes, año)
            $fechaActual = Carbon::createFromDate($request->año, $request->mes, $request->dia, $timezone);

            // Obtener el lunes de esa semana (inicio)
            $fechaInicio = $fechaActual->startOfWeek(Carbon::MONDAY)->copy()->startOfDay();

            // Obtener el domingo de esa semana (fin)
            $fechaFin = $fechaActual->endOfWeek(Carbon::SUNDAY)->copy()->endOfDay();

            // Asignar los valores de fecha de inicio
            $peticion['dia'] = $fechaInicio->day;
            $peticion['mes'] = $fechaInicio->month;
            $peticion['año'] = $fechaInicio->year;
        }

        $usuariosHoy = User::with('compras')
            ->whereBetween('created_at', [
                $fechaInicio->copy()->setTimezone('UTC'),
                $fechaFin->copy()->setTimezone('UTC')
            ])
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
        $usuariosUltimos30Dias = User::where('created_at', '>=', $fechaActual->copy()->subDays(30))->get();

        // Número de personas registradas hoy
        $registrosHoy = $usuariosHoy->count();

        // Promedio de registros en el mes actual
        $promedioMesActual = User::whereMonth('created_at', $peticion['mes'])
                                ->whereYear('created_at', $peticion['año'])
                                ->count() / $fechaActual->copy()->daysInMonth;

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
        $mesAnterior = $fechaActual->copy()->subMonth();
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
        $añoAnterior = $fechaActual->copy()->subYear();
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

        $chartUrl = 'https://quickchart.io/chart?c=' . urlencode(json_encode($chartConfig));
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

        $fechaInicioFormato = $fechaInicio->day."/".$fechaInicio->month."/".$fechaInicio->year;
        $fechaFinFormato = $fechaFin->day."/".$fechaFin->month."/".$fechaFin->year;

        return view('reportes.semanal.reporte', compact(
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
            'facturas'
        ));
    }

    public function getreportemensual(Request $request)
    {

        $timezone = 'America/Bogota';

        if(isset($request->dia)){

            $fechaActual = Carbon::createFromDate($request->año, $request->mes, $request->dia, $timezone);

            // Obtener el primer día del mes
            $fechaInicio = $fechaActual->copy()->startOfMonth()->startOfDay();

            // Obtener el último día del mes
            $fechaFin = $fechaActual->copy()->endOfMonth()->endOfDay();

            // Asignar los valores de fecha de inicio
            $peticion['dia'] = $fechaInicio->day;
            $peticion['mes'] = $fechaInicio->month;
            $peticion['año'] = $fechaInicio->year;

            // Obtener el nombre del mes en formato 'Mes Año' (Ej: 'Enero 2024')
            $nombreMes = ucfirst($fechaInicio->translatedFormat('F Y'));

        } else {

            $mesTexto = $request->mes;
            list($fechaInicioSTR, $fechaFinSTR) = convertirMesAFecha($mesTexto);
            $fechaInicio = Carbon::parse($fechaInicioSTR);
            $fechaFin = Carbon::parse($fechaFinSTR);

            $fechaActual = $fechaInicio->copy();

            $nombreMes = ucfirst($fechaInicio->translatedFormat('F Y'));

            $peticion['dia'] = $fechaInicio->day;
            $peticion['mes'] = $fechaInicio->month;
            $peticion['año'] = $fechaInicio->year;

        }

        $usuariosHoy = User::with('compras')->whereMonth('created_at', $peticion['mes'])
        ->whereYear('created_at', $peticion['año'])
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
        $usuariosUltimos30Dias = User::where('created_at', '>=', $fechaInicio)
        ->where('created_at', '<=', $fechaFin)
        ->get();

        // Número de personas registradas hoy
        $registrosHoy = $usuariosHoy->count();

        // Promedio de registros en el mes actual
        $promedioMesActual = User::whereBetween('created_at', [$fechaInicio, $fechaFin])
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
        $mesAnterior = $fechaActual->copy()->subMonth();
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
        $añoAnterior = $fechaActual->copy()->subYear();
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

        $chartUrl = 'https://quickchart.io/chart?c=' . urlencode(json_encode($chartConfig));
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

        return view('reportes.mensual.reporte', compact(
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
    $telegram = new Api(env('TELEGRAM_SAIME_BOT'));

    $response = $telegram->sendDocument([
        'chat_id' => "-".env('TELEGRAM_SAIME_GROUP'),
        'document' => InputFile::create(storage_path($filePDF)),
        'caption' => $nombre
    ]);

    $messageId = $response->getMessageId();
}

function convertirMesAFecha($mesTexto) {
    // Array de meses en español a su correspondiente número
    $meses = [
        'Enero' => '01',
        'Febrero' => '02',
        'Marzo' => '03',
        'Abril' => '04',
        'Mayo' => '05',
        'Junio' => '06',
        'Julio' => '07',
        'Agosto' => '08',
        'Septiembre' => '09',
        'Octubre' => '10',
        'Noviembre' => '11',
        'Diciembre' => '12',
    ];

    // Separar el mes y año
    list($mesNombre, $año) = explode(' de ', $mesTexto);

    // Obtener el número del mes
    $mesNumero = $meses[$mesNombre];

    // Generar la fecha de inicio y fin del mes
    $fechaInicio = "$año-$mesNumero-01";
    $fechaFin = date("Y-m-t", strtotime($fechaInicio)); // Obteniendo el último día del mes

    return [$fechaInicio, $fechaFin];
}

