<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\User;
use App\Models\Factura;
use App\Models\Task;
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
    public function dashboard(Request $request)
    {
        $month = (string) $request->input('month', now()->format('Y-m'));

        try {
            $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        } catch (\Throwable $e) {
            $start = today()->startOfMonth();
            $month = $start->format('Y-m');
        }

        $end = $start->copy()->endOfMonth();
        $chartEnd = $start->isSameMonth(today()) ? today() : $end;

        $labels = [];
        $dateKeys = [];
        $cursor = $start->copy();

        while ($cursor <= $chartEnd) {
            $dateKeys[] = $cursor->toDateString();
            $labels[] = $cursor->format('d/m');
            $cursor->addDay();
        }

        $registrationsByDay = $this->dailyRegistrations($start, $chartEnd);
        $registrationPaymentsByDay = $this->dailyRegistrationPayments($start, $chartEnd);

        $registrationsSeries = [];
        $paidSeries = [];
        $conversionPerTenSeries = [];
        $cumulativeRegistrations = [];
        $cumulativePaid = [];
        $runningRegistrations = 0;
        $runningPaid = 0;

        foreach ($dateKeys as $day) {
            $registrations = (int) ($registrationsByDay[$day] ?? 0);
            $paid = (int) ($registrationPaymentsByDay[$day] ?? 0);

            $registrationsSeries[] = $registrations;
            $paidSeries[] = $paid;
            $conversionPerTenSeries[] = $registrations > 0 ? round(($paid / $registrations) * 10, 2) : 0;

            $runningRegistrations += $registrations;
            $runningPaid += $paid;
            $cumulativeRegistrations[] = $runningRegistrations;
            $cumulativePaid[] = $runningPaid;
        }

        $registrationPayments = $this->registrationPaymentsSummary($start, $end);
        $registeredThisMonth = $this->externalUsersQuery()
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $conversionPerTen = $registeredThisMonth > 0
            ? round(((int) $registrationPayments->paid_clients / $registeredThisMonth) * 10, 2)
            : 0;

        $paymentMethods = $this->registrationPaymentsByMethod($start, $end);
        $serviceRevenue = $this->registrationPaymentsByService($start, $end);
        $taskMetrics = $this->taskMetrics($start, $end);
        $sellerOpenTasks = $this->sellerOpenTasks();
        $salesPipeline = $this->salesPipeline($start, $end);
        $cosMetrics = $this->cosMetrics();

        $kpis = [
            'registered_month' => $registeredThisMonth,
            'paid_registration_month' => (int) $registrationPayments->paid_clients,
            'registration_payment_amount' => (float) $registrationPayments->amount,
            'conversion_per_ten' => $conversionPerTen,
            'open_tasks' => $taskMetrics['open'],
            'overdue_tasks' => $taskMetrics['overdue'],
            'cos_ready' => $cosMetrics['ready'],
            'cos_with_data' => $cosMetrics['with_data'],
        ];

        $charts = [
            'labels' => $labels,
            'registrations' => $registrationsSeries,
            'paid' => $paidSeries,
            'conversion_per_ten' => $conversionPerTenSeries,
            'cumulative_registrations' => $cumulativeRegistrations,
            'cumulative_paid' => $cumulativePaid,
            'payment_methods' => [
                'labels' => $paymentMethods->pluck('method')->values(),
                'data' => $paymentMethods->pluck('total')->values(),
            ],
            'service_revenue' => [
                'labels' => $serviceRevenue->pluck('service')->values(),
                'data' => $serviceRevenue->pluck('amount')->values(),
            ],
            'seller_open_tasks' => [
                'labels' => $sellerOpenTasks->pluck('name')->values(),
                'data' => $sellerOpenTasks->pluck('total')->values(),
            ],
            'sales_pipeline' => [
                'labels' => $salesPipeline->pluck('label')->values(),
                'data' => $salesPipeline->pluck('total')->values(),
            ],
            'cos_stages' => [
                'labels' => $cosMetrics['stages']->pluck('stage')->values(),
                'data' => $cosMetrics['stages']->pluck('total')->values(),
            ],
            'cos_services' => [
                'labels' => $cosMetrics['services']->pluck('service')->values(),
                'data' => $cosMetrics['services']->pluck('total')->values(),
            ],
        ];

        return view('reportes.dashboard', compact(
            'month',
            'start',
            'end',
            'kpis',
            'charts',
            'taskMetrics',
            'cosMetrics',
            'serviceRevenue',
            'sellerOpenTasks',
            'salesPipeline'
        ));
    }

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
        $usuariosHoy = User::with('compras')->whereDate('created_at', $fechaActual)
        ->where('email', 'not like', '%sefarvzla%')
        ->where('email', 'not like', '%sefaruniversal%')
        ->where('name', 'not like', '%prueba%')->get();

        $facturas = json_decode(
            json_encode(
                Factura::whereHas('compras', function($query) {
                        $query->where('pagado', 1);
                    })
                    ->whereDate('created_at', $fechaActual)
                    ->with(['compras' => function($query) {
                        $query->where('pagado', 1)
                              ->select('servicio_hs_id', 'monto', 'hash_factura');
                    }])
                    ->get()
            ),
            true
        );

        // Usuarios registrados en los últimos 30 días
        $usuariosUltimos30Dias = User::where('created_at', '>=', $fechaActual->copy()->subDays(30))
        ->where('email', 'not like', '%sefarvzla%')
        ->where('email', 'not like', '%sefaruniversal%')
        ->where('name', 'not like', '%prueba%')->get();

        // Número de personas registradas hoy
        $registrosHoy = $usuariosHoy->count();

        // Promedio de registros en el mes actual
        $promedioMesActual = User::whereMonth('created_at', $peticion['mes'])
                                ->whereYear('created_at', $peticion['año'])->where('email', 'not like', '%sefarvzla%')
        ->where('email', 'not like', '%sefaruniversal%')
        ->where('name', 'not like', '%prueba%')
                                ->count() / $fechaActual->daysInMonth;

        $diaMasRegistrosMesActual = User::whereMonth('created_at', $peticion['mes'])
                                ->whereYear('created_at', $peticion['año'])->where('email', 'not like', '%sefarvzla%')
        ->where('email', 'not like', '%sefaruniversal%')
        ->where('name', 'not like', '%prueba%')
                                ->selectRaw('DAY(created_at) as dia, COUNT(*) as registros')
                                ->groupBy('dia')
                                ->orderBy('registros', 'desc')
                                ->first();

        $diaMenosRegistrosMesActual = User::whereMonth('created_at', $peticion['mes'])
                                 ->whereYear('created_at', $peticion['año'])->where('email', 'not like', '%sefarvzla%')
        ->where('email', 'not like', '%sefaruniversal%')
        ->where('name', 'not like', '%prueba%')
                                 ->selectRaw('DAY(created_at) as dia, COUNT(*) as registros')
                                 ->groupBy('dia')
                                 ->orderBy('registros', 'asc')
                                 ->first();


        // Promedio de registros en el mes anterior
        $mesAnterior = $fechaActual->copy()->subMonth();
        $promedioMesAnterior = User::whereMonth('created_at', $mesAnterior->month)
                                   ->whereYear('created_at', $mesAnterior->year)->where('email', 'not like', '%sefarvzla%')
        ->where('email', 'not like', '%sefaruniversal%')
        ->where('name', 'not like', '%prueba%')
                                   ->count() / $mesAnterior->daysInMonth;

        $diaMasRegistrosMesAnterior = User::whereMonth('created_at', $mesAnterior->month)
                                ->whereYear('created_at', $mesAnterior->year)->where('email', 'not like', '%sefarvzla%')
        ->where('email', 'not like', '%sefaruniversal%')
        ->where('name', 'not like', '%prueba%')
                                ->selectRaw('DAY(created_at) as dia, COUNT(*) as registros')
                                ->groupBy('dia')
                                ->orderBy('registros', 'desc')
                                ->first();

        $diaMenosRegistrosMesAnterior = User::whereMonth('created_at', $mesAnterior->month)
                                 ->whereYear('created_at', $mesAnterior->year)->where('email', 'not like', '%sefarvzla%')
        ->where('email', 'not like', '%sefaruniversal%')
        ->where('name', 'not like', '%prueba%')
                                 ->selectRaw('DAY(created_at) as dia, COUNT(*) as registros')
                                 ->groupBy('dia')
                                 ->orderBy('registros', 'asc')
                                 ->first();

        // Promedio de registros en el mismo mes del año anterior
        $añoAnterior = $fechaActual->copy()->subYear();
        $promedioMismoMesAñoAnterior = User::whereMonth('created_at', $peticion['mes'])
                                            ->whereYear('created_at', $añoAnterior->year)->where('email', 'not like', '%sefarvzla%')
        ->where('email', 'not like', '%sefaruniversal%')
        ->where('name', 'not like', '%prueba%')
                                            ->count() / $fechaActual->daysInMonth;

        $diaMasRegistrosMesActual_aa = User::whereMonth('created_at', $peticion['mes'])
                                ->whereYear('created_at', $añoAnterior->year)->where('email', 'not like', '%sefarvzla%')
        ->where('email', 'not like', '%sefaruniversal%')
        ->where('name', 'not like', '%prueba%')
                                ->selectRaw('DAY(created_at) as dia, COUNT(*) as registros')
                                ->groupBy('dia')
                                ->orderBy('registros', 'desc')
                                ->first();

        $diaMenosRegistrosMesActual_aa = User::whereMonth('created_at', $peticion['mes'])
                                ->whereYear('created_at', $añoAnterior->year)->where('email', 'not like', '%sefarvzla%')
        ->where('email', 'not like', '%sefaruniversal%')
        ->where('name', 'not like', '%prueba%')
                                ->selectRaw('DAY(created_at) as dia, COUNT(*) as registros')
                                ->groupBy('dia')
                                ->orderBy('registros', 'asc')
                                ->first();

        // Promedio de registros en el mes anterior al mes actual del año anterior
        $mesAnteriorAñoAnterior = $añoAnterior->subMonth();
        $promedioMesAnteriorAñoAnterior = User::whereMonth('created_at', $mesAnteriorAñoAnterior->month)
                                               ->whereYear('created_at', $mesAnteriorAñoAnterior->year)->where('email', 'not like', '%sefarvzla%')
        ->where('email', 'not like', '%sefaruniversal%')
        ->where('name', 'not like', '%prueba%')
                                               ->count() / $mesAnteriorAñoAnterior->daysInMonth;

        $diaMasRegistrosMesAnterior_aa = User::whereMonth('created_at', $mesAnteriorAñoAnterior->month)
                                ->whereYear('created_at', $mesAnteriorAñoAnterior->year)->where('email', 'not like', '%sefarvzla%')
        ->where('email', 'not like', '%sefaruniversal%')
        ->where('name', 'not like', '%prueba%')
                                ->selectRaw('DAY(created_at) as dia, COUNT(*) as registros')
                                ->groupBy('dia')
                                ->orderBy('registros', 'desc')
                                ->first();

        $diaMenosRegistrosMesAnterior_aa = User::whereMonth('created_at', $mesAnteriorAñoAnterior->month)
                                ->whereYear('created_at', $mesAnteriorAñoAnterior->year)->where('email', 'not like', '%sefarvzla%')
        ->where('email', 'not like', '%sefaruniversal%')
        ->where('name', 'not like', '%prueba%')
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
            "
                SELECT DATE(created_at) as date, COUNT(*) as count
                FROM users
                WHERE created_at >= :lastMonth AND created_at < :fechaSiguiente
                GROUP BY DATE(created_at)
                ORDER BY date ASC
            ",
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
                        ->whereYear('created_at', $peticion['año'])->where('email', 'not like', '%sefarvzla%')
        ->where('email', 'not like', '%sefaruniversal%')
        ->where('name', 'not like', '%prueba%')
                        ->count()
            ],
            'mes_anterior' => [
                'promedio' => round($promedioMesAnterior, 2),
                'maximo' => $diaMasRegistrosMesAnterior->registros,
                'minimo' => $diaMenosRegistrosMesAnterior->registros,
                'total' => User::whereMonth('created_at', $peticion['mes'] == 1 ? 12 : $peticion['mes'] - 1)
                        ->whereYear('created_at', $peticion['año'])->where('email', 'not like', '%sefarvzla%')
        ->where('email', 'not like', '%sefaruniversal%')
        ->where('name', 'not like', '%prueba%')
                        ->count()
            ],
            'mes_actual_aa' => [
                'promedio' => round($promedioMismoMesAñoAnterior, 2),
                'maximo' => $diaMasRegistrosMesActual_aa->registros,
                'minimo' => $diaMenosRegistrosMesActual_aa->registros,
                'total' => User::whereMonth('created_at', $peticion['mes'])
                        ->whereYear('created_at', $añoAnterior->year)->where('email', 'not like', '%sefarvzla%')
        ->where('email', 'not like', '%sefaruniversal%')
        ->where('name', 'not like', '%prueba%')
                        ->count()
            ],
            'mes_anterior_aa' => [
                'promedio' => round($promedioMesAnteriorAñoAnterior, 2),
                'maximo' => $diaMasRegistrosMesAnterior_aa->registros,
                'minimo' => $diaMenosRegistrosMesAnterior_aa->registros,
                'total' => User::whereMonth('created_at', $peticion['mes'] == 1 ? 12 : $peticion['mes'] - 1)
                        ->whereYear('created_at', $peticion['año']-1)->where('email', 'not like', '%sefarvzla%')
        ->where('email', 'not like', '%sefaruniversal%')
        ->where('name', 'not like', '%prueba%')
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
            ->where('email', 'not like', '%sefarvzla%')
            ->where('email', 'not like', '%sefaruniversal%')
            ->where('name', 'not like', '%prueba%')
            ->get();

        $facturas = json_decode(
            json_encode(
                Factura::whereIn('met', ['stripe', 'paypal'])
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

        $facturasCupones = json_decode(
            json_encode(
                Factura::where('met', 'cupon')
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
        $usuariosUltimos30Dias = User::where('created_at', '>=', $fechaActual->copy()->subDays(30))->where('email', 'not like', '%sefarvzla%')
        ->where('email', 'not like', '%sefaruniversal%')
        ->where('name', 'not like', '%prueba%')->get();

        // Número de personas registradas hoy
        $registrosHoy = $usuariosHoy->count();

        // Promedio de registros en el mes actual
        $promedioMesActual = User::whereMonth('created_at', $peticion['mes'])
                                ->whereYear('created_at', $peticion['año'])->where('email', 'not like', '%sefarvzla%')
        ->where('email', 'not like', '%sefaruniversal%')
        ->where('name', 'not like', '%prueba%')
                                ->count() / $fechaActual->copy()->daysInMonth;

        $diaMasRegistrosMesActual = User::whereMonth('created_at', $peticion['mes'])
                                ->whereYear('created_at', $peticion['año'])->where('email', 'not like', '%sefarvzla%')
        ->where('email', 'not like', '%sefaruniversal%')
        ->where('name', 'not like', '%prueba%')
                                ->selectRaw('DAY(created_at) as dia, COUNT(*) as registros')
                                ->groupBy('dia')
                                ->orderBy('registros', 'desc')
                                ->first();

        $diaMenosRegistrosMesActual = User::whereMonth('created_at', $peticion['mes'])
                                    ->whereYear('created_at', $peticion['año'])->where('email', 'not like', '%sefarvzla%')
        ->where('email', 'not like', '%sefaruniversal%')
        ->where('name', 'not like', '%prueba%')
                                    ->selectRaw('DAY(created_at) as dia, COUNT(*) as registros')
                                    ->groupBy('dia')
                                    ->orderBy('registros', 'asc')
                                    ->first();


        // Promedio de registros en el mes anterior
        $mesAnterior = $fechaActual->copy()->subMonth();
        $promedioMesAnterior = User::whereMonth('created_at', $mesAnterior->month)
                                    ->whereYear('created_at', $mesAnterior->year)->where('email', 'not like', '%sefarvzla%')
        ->where('email', 'not like', '%sefaruniversal%')
        ->where('name', 'not like', '%prueba%')
                                    ->count() / $mesAnterior->daysInMonth;

        $diaMasRegistrosMesAnterior = User::whereMonth('created_at', $mesAnterior->month)
                                ->whereYear('created_at', $mesAnterior->year)->where('email', 'not like', '%sefarvzla%')
        ->where('email', 'not like', '%sefaruniversal%')
        ->where('name', 'not like', '%prueba%')
                                ->selectRaw('DAY(created_at) as dia, COUNT(*) as registros')
                                ->groupBy('dia')
                                ->orderBy('registros', 'desc')
                                ->first();

        $diaMenosRegistrosMesAnterior = User::whereMonth('created_at', $mesAnterior->month)
                                    ->whereYear('created_at', $mesAnterior->year)->where('email', 'not like', '%sefarvzla%')
        ->where('email', 'not like', '%sefaruniversal%')
        ->where('name', 'not like', '%prueba%')
                                    ->selectRaw('DAY(created_at) as dia, COUNT(*) as registros')
                                    ->groupBy('dia')
                                    ->orderBy('registros', 'asc')
                                    ->first();

        // Promedio de registros en el mismo mes del año anterior
        $añoAnterior = $fechaActual->copy()->subYear();
        $promedioMismoMesAñoAnterior = User::whereMonth('created_at', $peticion['mes'])
                                            ->whereYear('created_at', $añoAnterior->year)->where('email', 'not like', '%sefarvzla%')
        ->where('email', 'not like', '%sefaruniversal%')
        ->where('name', 'not like', '%prueba%')
                                            ->count() / $fechaActual->daysInMonth;

        $diaMasRegistrosMesActual_aa = User::whereMonth('created_at', $peticion['mes'])
                                ->whereYear('created_at', $añoAnterior->year)->where('email', 'not like', '%sefarvzla%')
        ->where('email', 'not like', '%sefaruniversal%')
        ->where('name', 'not like', '%prueba%')
                                ->selectRaw('DAY(created_at) as dia, COUNT(*) as registros')
                                ->groupBy('dia')
                                ->orderBy('registros', 'desc')
                                ->first();

        $diaMenosRegistrosMesActual_aa = User::whereMonth('created_at', $peticion['mes'])
                                ->whereYear('created_at', $añoAnterior->year)->where('email', 'not like', '%sefarvzla%')
        ->where('email', 'not like', '%sefaruniversal%')
        ->where('name', 'not like', '%prueba%')
                                ->selectRaw('DAY(created_at) as dia, COUNT(*) as registros')
                                ->groupBy('dia')
                                ->orderBy('registros', 'asc')
                                ->first();

        // Promedio de registros en el mes anterior al mes actual del año anterior
        $mesAnteriorAñoAnterior = $añoAnterior->subMonth();
        $promedioMesAnteriorAñoAnterior = User::whereMonth('created_at', $mesAnteriorAñoAnterior->month)
                                                ->whereYear('created_at', $mesAnteriorAñoAnterior->year)->where('email', 'not like', '%sefarvzla%')
        ->where('email', 'not like', '%sefaruniversal%')
        ->where('name', 'not like', '%prueba%')
                                                ->count() / $mesAnteriorAñoAnterior->daysInMonth;

        $diaMasRegistrosMesAnterior_aa = User::whereMonth('created_at', $mesAnteriorAñoAnterior->month)
                                ->whereYear('created_at', $mesAnteriorAñoAnterior->year)->where('email', 'not like', '%sefarvzla%')
        ->where('email', 'not like', '%sefaruniversal%')
        ->where('name', 'not like', '%prueba%')
                                ->selectRaw('DAY(created_at) as dia, COUNT(*) as registros')
                                ->groupBy('dia')
                                ->orderBy('registros', 'desc')
                                ->first();

        $diaMenosRegistrosMesAnterior_aa = User::whereMonth('created_at', $mesAnteriorAñoAnterior->month)
                                ->whereYear('created_at', $mesAnteriorAñoAnterior->year)->where('email', 'not like', '%sefarvzla%')
        ->where('email', 'not like', '%sefaruniversal%')
        ->where('name', 'not like', '%prueba%')
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
            "
                SELECT DATE(created_at) as date, COUNT(*) as count
                FROM users
                WHERE created_at >= :lastMonth AND created_at < :fechaSiguiente
                GROUP BY DATE(created_at)
                ORDER BY date ASC
            ",
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
                        ->whereYear('created_at', $peticion['año'])->where('email', 'not like', '%sefarvzla%')
        ->where('email', 'not like', '%sefaruniversal%')
        ->where('name', 'not like', '%prueba%')
                        ->count()
            ],
            'mes_anterior' => [
                'promedio' => round($promedioMesAnterior, 2),
                'maximo' => $diaMasRegistrosMesAnterior->registros,
                'minimo' => $diaMenosRegistrosMesAnterior->registros,
                'total' => User::whereMonth('created_at', $peticion['mes'] == 1 ? 12 : $peticion['mes'] - 1)
                        ->whereYear('created_at', $peticion['año'])->where('email', 'not like', '%sefarvzla%')
        ->where('email', 'not like', '%sefaruniversal%')
        ->where('name', 'not like', '%prueba%')
                        ->count()
            ],
            'mes_actual_aa' => [
                'promedio' => round($promedioMismoMesAñoAnterior, 2),
                'maximo' => $diaMasRegistrosMesActual_aa->registros,
                'minimo' => $diaMenosRegistrosMesActual_aa->registros,
                'total' => User::whereMonth('created_at', $peticion['mes'])
                        ->whereYear('created_at', $añoAnterior->year)->where('email', 'not like', '%sefarvzla%')
        ->where('email', 'not like', '%sefaruniversal%')
        ->where('name', 'not like', '%prueba%')
                        ->count()
            ],
            'mes_anterior_aa' => [
                'promedio' => round($promedioMesAnteriorAñoAnterior, 2),
                'maximo' => $diaMasRegistrosMesAnterior_aa->registros,
                'minimo' => $diaMenosRegistrosMesAnterior_aa->registros,
                'total' => User::whereMonth('created_at', $peticion['mes'] == 1 ? 12 : $peticion['mes'] - 1)
                        ->whereYear('created_at', $peticion['año']-1)->where('email', 'not like', '%sefarvzla%')
        ->where('email', 'not like', '%sefaruniversal%')
        ->where('name', 'not like', '%prueba%')
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
            'facturas',
            'facturasCupones'
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
        ->where('email', 'not like', '%sefarvzla%')
        ->where('email', 'not like', '%sefaruniversal%')
        ->where('name', 'not like', '%prueba%')
            ->get();

        $facturas = json_decode(
            json_encode(
                Factura::whereIn('met', ['stripe', 'paypal'])
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

        $facturasCupones = json_decode(
            json_encode(
                Factura::where('met', 'cupon')
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
        ->where('created_at', '<=', $fechaFin)->where('email', 'not like', '%sefarvzla%')
        ->where('email', 'not like', '%sefaruniversal%')
        ->where('name', 'not like', '%prueba%')
        ->get();

        // Número de personas registradas hoy
        $registrosHoy = $usuariosHoy->count();

        // Promedio de registros en el mes actual
        $promedioMesActual = User::whereBetween('created_at', [$fechaInicio, $fechaFin])->where('email', 'not like', '%sefarvzla%')
        ->where('email', 'not like', '%sefaruniversal%')
        ->where('name', 'not like', '%prueba%')
                                ->count() / $fechaActual->daysInMonth;

        $diaMasRegistrosMesActual = User::whereMonth('created_at', $peticion['mes'])
                                ->whereYear('created_at', $peticion['año'])->where('email', 'not like', '%sefarvzla%')
        ->where('email', 'not like', '%sefaruniversal%')
        ->where('name', 'not like', '%prueba%')
                                ->selectRaw('DAY(created_at) as dia, COUNT(*) as registros')
                                ->groupBy('dia')
                                ->orderBy('registros', 'desc')
                                ->first();

        $diaMenosRegistrosMesActual = User::whereMonth('created_at', $peticion['mes'])
                                    ->whereYear('created_at', $peticion['año'])->where('email', 'not like', '%sefarvzla%')
        ->where('email', 'not like', '%sefaruniversal%')
        ->where('name', 'not like', '%prueba%')
                                    ->selectRaw('DAY(created_at) as dia, COUNT(*) as registros')
                                    ->groupBy('dia')
                                    ->orderBy('registros', 'asc')
                                    ->first();


        // Promedio de registros en el mes anterior
        $mesAnterior = $fechaActual->copy()->subMonth();
        $promedioMesAnterior = User::whereMonth('created_at', $mesAnterior->month)
                                    ->whereYear('created_at', $mesAnterior->year)->where('email', 'not like', '%sefarvzla%')
        ->where('email', 'not like', '%sefaruniversal%')
        ->where('name', 'not like', '%prueba%')
                                    ->count() / $mesAnterior->daysInMonth;

        $diaMasRegistrosMesAnterior = User::whereMonth('created_at', $mesAnterior->month)
                                ->whereYear('created_at', $mesAnterior->year)->where('email', 'not like', '%sefarvzla%')
        ->where('email', 'not like', '%sefaruniversal%')
        ->where('name', 'not like', '%prueba%')
                                ->selectRaw('DAY(created_at) as dia, COUNT(*) as registros')
                                ->groupBy('dia')
                                ->orderBy('registros', 'desc')
                                ->first();

        $diaMenosRegistrosMesAnterior = User::whereMonth('created_at', $mesAnterior->month)
                                    ->whereYear('created_at', $mesAnterior->year)->where('email', 'not like', '%sefarvzla%')
        ->where('email', 'not like', '%sefaruniversal%')
        ->where('name', 'not like', '%prueba%')
                                    ->selectRaw('DAY(created_at) as dia, COUNT(*) as registros')
                                    ->groupBy('dia')
                                    ->orderBy('registros', 'asc')
                                    ->first();

        // Promedio de registros en el mismo mes del año anterior
        $añoAnterior = $fechaActual->copy()->subYear();
        $promedioMismoMesAñoAnterior = User::whereMonth('created_at', $peticion['mes'])
                                            ->whereYear('created_at', $añoAnterior->year)->where('email', 'not like', '%sefarvzla%')
        ->where('email', 'not like', '%sefaruniversal%')
        ->where('name', 'not like', '%prueba%')
                                            ->count() / $fechaActual->daysInMonth;

        $diaMasRegistrosMesActual_aa = User::whereMonth('created_at', $peticion['mes'])
                                ->whereYear('created_at', $añoAnterior->year)->where('email', 'not like', '%sefarvzla%')
        ->where('email', 'not like', '%sefaruniversal%')
        ->where('name', 'not like', '%prueba%')
                                ->selectRaw('DAY(created_at) as dia, COUNT(*) as registros')
                                ->groupBy('dia')
                                ->orderBy('registros', 'desc')
                                ->first();

        $diaMenosRegistrosMesActual_aa = User::whereMonth('created_at', $peticion['mes'])
                                ->whereYear('created_at', $añoAnterior->year)->where('email', 'not like', '%sefarvzla%')
        ->where('email', 'not like', '%sefaruniversal%')
        ->where('name', 'not like', '%prueba%')
                                ->selectRaw('DAY(created_at) as dia, COUNT(*) as registros')
                                ->groupBy('dia')
                                ->orderBy('registros', 'asc')
                                ->first();

        // Promedio de registros en el mes anterior al mes actual del año anterior
        $mesAnteriorAñoAnterior = $añoAnterior->subMonth();
        $promedioMesAnteriorAñoAnterior = User::whereMonth('created_at', $mesAnteriorAñoAnterior->month)
                                                ->whereYear('created_at', $mesAnteriorAñoAnterior->year)->where('email', 'not like', '%sefarvzla%')
        ->where('email', 'not like', '%sefaruniversal%')
        ->where('name', 'not like', '%prueba%')
                                                ->count() / $mesAnteriorAñoAnterior->daysInMonth;

        $diaMasRegistrosMesAnterior_aa = User::whereMonth('created_at', $mesAnteriorAñoAnterior->month)
                                ->whereYear('created_at', $mesAnteriorAñoAnterior->year)->where('email', 'not like', '%sefarvzla%')
        ->where('email', 'not like', '%sefaruniversal%')
        ->where('name', 'not like', '%prueba%')
                                ->selectRaw('DAY(created_at) as dia, COUNT(*) as registros')
                                ->groupBy('dia')
                                ->orderBy('registros', 'desc')
                                ->first();

        $diaMenosRegistrosMesAnterior_aa = User::whereMonth('created_at', $mesAnteriorAñoAnterior->month)
                                ->whereYear('created_at', $mesAnteriorAñoAnterior->year)->where('email', 'not like', '%sefarvzla%')
        ->where('email', 'not like', '%sefaruniversal%')
        ->where('name', 'not like', '%prueba%')
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
            "
                SELECT DATE(created_at) as date, COUNT(*) as count
                FROM users
                WHERE created_at >= :lastMonth AND created_at < :fechaSiguiente
                GROUP BY DATE(created_at)
                ORDER BY date ASC
            ",
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
                        ->whereYear('created_at', $peticion['año'])->where('email', 'not like', '%sefarvzla%')
        ->where('email', 'not like', '%sefaruniversal%')
        ->where('name', 'not like', '%prueba%')
                        ->count()
            ],
            'mes_anterior' => [
                'promedio' => round($promedioMesAnterior, 2),
                'maximo' => $diaMasRegistrosMesAnterior->registros,
                'minimo' => $diaMenosRegistrosMesAnterior->registros,
                'total' => User::whereMonth('created_at', $peticion['mes'] == 1 ? 12 : $peticion['mes'] - 1)
                        ->whereYear('created_at', $peticion['año'])->where('email', 'not like', '%sefarvzla%')
        ->where('email', 'not like', '%sefaruniversal%')
        ->where('name', 'not like', '%prueba%')
                        ->count()
            ],
            'mes_actual_aa' => [
                'promedio' => round($promedioMismoMesAñoAnterior, 2),
                'maximo' => $diaMasRegistrosMesActual_aa->registros,
                'minimo' => $diaMenosRegistrosMesActual_aa->registros,
                'total' => User::whereMonth('created_at', $peticion['mes'])
                        ->whereYear('created_at', $añoAnterior->year)->where('email', 'not like', '%sefarvzla%')
        ->where('email', 'not like', '%sefaruniversal%')
        ->where('name', 'not like', '%prueba%')
                        ->count()
            ],
            'mes_anterior_aa' => [
                'promedio' => round($promedioMesAnteriorAñoAnterior, 2),
                'maximo' => $diaMasRegistrosMesAnterior_aa->registros,
                'minimo' => $diaMenosRegistrosMesAnterior_aa->registros,
                'total' => User::whereMonth('created_at', $peticion['mes'] == 1 ? 12 : $peticion['mes'] - 1)
                        ->whereYear('created_at', $peticion['año']-1)->where('email', 'not like', '%sefarvzla%')
        ->where('email', 'not like', '%sefaruniversal%')
        ->where('name', 'not like', '%prueba%')
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
            'facturas',
            'facturasCupones'
        ));
    }

    public function getreporteanual(Request $request)
    {
        $anio = $request->input('anio', date('Y')); // Año solicitado, por defecto el año actual
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

        $chartUrl = 'https://quickchart.io/chart?c=' . urlencode(json_encode($chartConfig));
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
        $facturas = Factura::whereIn('met', ['stripe', 'paypal'])
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

        return view('reportes.anual.reporte', compact(
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
    }

    public function makeReport() {
        $dayscount = "4";
        $path = "/pdfReportes/";

        $globalcount = json_decode(json_encode(DB::select("SELECT DATE_FORMAT(DATE(`created_at`), '%M') as nombre, DATE_FORMAT(DATE(`created_at`), '%Y') as fecha, COUNT(id) as contador FROM users GROUP BY YEAR(`created_at`), MONTH(`created_at`);")),true);

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

        $users = DB::select("SELECT a.*, b.nombre as nombre_referido FROM users as a, hs_referidos as b WHERE b.correo=a.referido_por and a.created_at LIKE '%".$yesterdayquery."%' GROUP BY a.passport");

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

            $users = DB::select("SELECT a.*, b.nombre as nombre_referido, b.tipo as tipo_referido FROM users as a, hs_referidos as b WHERE b.correo=a.referido_por and a.created_at>='".$owago."' AND a.created_at<='".$yesterdayw."' GROUP BY a.passport;");

            $users_ftb = DB::select("SELECT a.*, b.nombre as nombre_referido, b.tipo as tipo_referido FROM users as a, hs_referidos as b WHERE b.correo=a.referido_por and a.created_at>='".$year."-01-01' AND a.created_at<='".$yesterdayw."' GROUP BY a.passport;");

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

    private function externalUsersQuery()
    {
        return $this->applyExternalUserFilters(User::query());
    }

    private function applyExternalUserFilters($query, string $prefix = '')
    {
        return $query
            ->where($prefix . 'email', 'not like', '%sefarvzla%')
            ->where($prefix . 'email', 'not like', '%sefaruniversal%')
            ->where($prefix . 'name', 'not like', '%prueba%');
    }

    private function registrationPaymentsQuery()
    {
        $query = DB::table('compras')
            ->join('facturas', 'facturas.hash_factura', '=', 'compras.hash_factura')
            ->join('users', 'users.id', '=', 'compras.id_user')
            ->where('compras.pagado', 1)
            ->whereNull('compras.deal_id');

        return $this->applyExternalUserFilters($query, 'users.');
    }

    private function dailyRegistrations(Carbon $start, Carbon $end)
    {
        return $this->externalUsersQuery()
            ->whereBetween('created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');
    }

    private function dailyRegistrationPayments(Carbon $start, Carbon $end)
    {
        return $this->registrationPaymentsQuery()
            ->whereBetween('facturas.created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->selectRaw('DATE(facturas.created_at) as day, COUNT(DISTINCT compras.id_user) as total')
            ->groupBy('day')
            ->pluck('total', 'day');
    }

    private function registrationPaymentsSummary(Carbon $start, Carbon $end): object
    {
        return $this->registrationPaymentsQuery()
            ->whereBetween('facturas.created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->selectRaw('COUNT(DISTINCT compras.id_user) as paid_clients')
            ->selectRaw('COUNT(*) as purchases')
            ->selectRaw('COALESCE(SUM(compras.monto), 0) as amount')
            ->first() ?? (object) [
                'paid_clients' => 0,
                'purchases' => 0,
                'amount' => 0,
            ];
    }

    private function registrationPaymentsByMethod(Carbon $start, Carbon $end)
    {
        return $this->registrationPaymentsQuery()
            ->whereBetween('facturas.created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->selectRaw("COALESCE(NULLIF(facturas.met, ''), 'Sin metodo') as method")
            ->selectRaw('COUNT(DISTINCT compras.id_user) as total')
            ->groupBy('facturas.met')
            ->orderByDesc('total')
            ->limit(6)
            ->get();
    }

    private function registrationPaymentsByService(Carbon $start, Carbon $end)
    {
        return $this->registrationPaymentsQuery()
            ->whereBetween('facturas.created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->selectRaw("COALESCE(NULLIF(compras.servicio_hs_id, ''), 'Sin servicio') as service")
            ->selectRaw('COALESCE(SUM(compras.monto), 0) as amount')
            ->groupBy('compras.servicio_hs_id')
            ->orderByDesc('amount')
            ->limit(8)
            ->get();
    }

    private function taskMetrics(Carbon $start, Carbon $end): array
    {
        $systemsUserIds = Task::systemsUserIds();

        $openQuery = Task::query()
            ->whereIn('status', [Task::STATUS_PENDING, Task::STATUS_IN_PROGRESS])
            ->when(! empty($systemsUserIds), function ($query) use ($systemsUserIds) {
                $query->whereNotIn('user_id', $systemsUserIds);
            });

        return [
            'open' => (clone $openQuery)->count(),
            'overdue' => (clone $openQuery)
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', today())
                ->count(),
            'completed_month' => Task::query()
                ->when(! empty($systemsUserIds), function ($query) use ($systemsUserIds) {
                    $query->whereNotIn('user_id', $systemsUserIds);
                })
                ->where('status', Task::STATUS_COMPLETED)
                ->whereBetween('updated_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
                ->count(),
            'responded_month' => Task::query()
                ->when(! empty($systemsUserIds), function ($query) use ($systemsUserIds) {
                    $query->whereNotIn('user_id', $systemsUserIds);
                })
                ->where('customer_responded', true)
                ->whereBetween('updated_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
                ->count(),
            'effective_month' => Task::query()
                ->when(! empty($systemsUserIds), function ($query) use ($systemsUserIds) {
                    $query->whereNotIn('user_id', $systemsUserIds);
                })
                ->where('call_effective', true)
                ->whereBetween('updated_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
                ->count(),
        ];
    }

    private function sellerOpenTasks()
    {
        $systemsUserIds = Task::systemsUserIds();

        return Task::query()
            ->join('users as advisors', 'advisors.id', '=', 'tasks.user_id')
            ->whereIn('tasks.status', [Task::STATUS_PENDING, Task::STATUS_IN_PROGRESS])
            ->when(! empty($systemsUserIds), function ($query) use ($systemsUserIds) {
                $query->whereNotIn('tasks.user_id', $systemsUserIds);
            })
            ->selectRaw('advisors.name as name, COUNT(*) as total')
            ->groupBy('advisors.id', 'advisors.name')
            ->orderByDesc('total')
            ->limit(8)
            ->get();
    }

    private function salesPipeline(Carbon $start, Carbon $end)
    {
        $counts = Task::query()
            ->whereNotNull('sale_status')
            ->whereBetween('updated_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->selectRaw('sale_status, COUNT(*) as total')
            ->groupBy('sale_status')
            ->pluck('total', 'sale_status');

        return collect(Task::saleStatusOptions())
            ->map(fn ($label, $key) => [
                'key' => $key,
                'label' => $label,
                'total' => (int) ($counts[$key] ?? 0),
            ])
            ->values();
    }

    private function cosMetrics(): array
    {
        $withDataQuery = $this->externalUsersQuery()->whereNotNull('arraycos');
        $withData = (clone $withDataQuery)->count();

        $cosUsers = (clone $withDataQuery)
            ->select('id', 'name', 'email', 'arraycos', 'arraycos_expire', 'cosready')
            ->orderByDesc('arraycos_expire')
            ->limit(3000)
            ->get();

        $stageCounts = [];
        $serviceCounts = [];
        $recent = collect();
        $warnings = 0;

        foreach ($cosUsers as $user) {
            foreach (($user->arraycos ?? []) as $process) {
                if (! is_array($process)) {
                    continue;
                }

                $stage = trim((string) ($process['currentStepName'] ?? $process['description'] ?? 'Sin etapa'));
                $service = trim((string) ($process['servicio'] ?? 'Sin servicio'));

                $stage = $stage !== '' ? $stage : 'Sin etapa';
                $service = $service !== '' ? $service : 'Sin servicio';

                $stageCounts[$stage] = ($stageCounts[$stage] ?? 0) + 1;
                $serviceCounts[$service] = ($serviceCounts[$service] ?? 0) + 1;

                if (! empty(strip_tags((string) ($process['warning'] ?? '')))) {
                    $warnings++;
                }

                if ($recent->count() < 8) {
                    $recent->push([
                        'user_id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'service' => $service,
                        'stage' => $stage,
                        'expires_at' => $user->arraycos_expire,
                        'ready' => (bool) $user->cosready,
                    ]);
                }
            }
        }

        $stages = collect($stageCounts)
            ->map(fn ($total, $stage) => ['stage' => $stage, 'total' => $total])
            ->sortByDesc('total')
            ->values()
            ->take(8);

        $services = collect($serviceCounts)
            ->map(fn ($total, $service) => ['service' => $service, 'total' => $total])
            ->sortByDesc('total')
            ->values()
            ->take(8);

        return [
            'with_data' => $withData,
            'ready' => $this->externalUsersQuery()->where('cosready', 1)->count(),
            'fresh' => (clone $withDataQuery)->where('arraycos_expire', '>=', now())->count(),
            'expired' => (clone $withDataQuery)->where('arraycos_expire', '<', now())->count(),
            'warnings' => $warnings,
            'stages' => $stages,
            'services' => $services,
            'recent' => $recent,
        ];
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

