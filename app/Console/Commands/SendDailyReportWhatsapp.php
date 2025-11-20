<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PDF;
use Mail;
use App\Models\User;
use App\Models\Factura;
use App\Models\Compras;
use App\Models\WhatsappBotURL;
use App\Models\ReportPhoneNumbers;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Telegram\Bot\Api;
use Telegram\Bot\FileUpload\InputFile;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class SendDailyReportWhatsapp extends Command
{
    // Nombre del comando
    protected $signature = 'send:dailyreportwhatsapp';

    // Descripción del comando
    protected $description = 'Genera el reporte diario y lo envía por correo a los destinatarios correspondientes';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $peticion = [];
        $fechaActual = Carbon::now()->subDay();  // Restar un día (fecha de ayer)
        $peticion['dia'] = $fechaActual->day;
        $peticion['mes'] = $fechaActual->month;
        $peticion['año'] = $fechaActual->year;

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
                                ->whereYear('created_at', $peticion['año'])
                                ->where('email', 'not like', '%sefarvzla%')
                                ->where('email', 'not like', '%sefaruniversal%')
                                ->where('name', 'not like', '%prueba%')
                                ->count() / $fechaActual->daysInMonth;

        $diaMasRegistrosMesActual = User::whereMonth('created_at', $peticion['mes'])
                                ->whereYear('created_at', $peticion['año'])
                                ->where('email', 'not like', '%sefarvzla%')
                                ->where('email', 'not like', '%sefaruniversal%')
                                ->where('name', 'not like', '%prueba%')
                                ->selectRaw('DAY(created_at) as dia, COUNT(*) as registros')
                                ->groupBy('dia')
                                ->orderBy('registros', 'desc')
                                ->first();

        $diaMenosRegistrosMesActual = User::whereMonth('created_at', $peticion['mes'])
                                 ->whereYear('created_at', $peticion['año'])
                                 ->where('email', 'not like', '%sefarvzla%')
                                 ->where('email', 'not like', '%sefaruniversal%')
                                 ->where('name', 'not like', '%prueba%')
                                 ->selectRaw('DAY(created_at) as dia, COUNT(*) as registros')
                                 ->groupBy('dia')
                                 ->orderBy('registros', 'asc')
                                 ->first();


        // Promedio de registros en el mes anterior
        $mesAnterior = $fechaActual->copy()->subMonth();
        $promedioMesAnterior = User::whereMonth('created_at', $mesAnterior->month)
                                   ->whereYear('created_at', $mesAnterior->year)
                                   ->where('email', 'not like', '%sefarvzla%')
                                   ->where('email', 'not like', '%sefaruniversal%')
                                   ->where('name', 'not like', '%prueba%')
                                   ->count() / $mesAnterior->daysInMonth;

        $diaMasRegistrosMesAnterior = User::whereMonth('created_at', $mesAnterior->month)
                                ->whereYear('created_at', $mesAnterior->year)
                                ->where('email', 'not like', '%sefarvzla%')
                                ->where('email', 'not like', '%sefaruniversal%')
                                ->where('name', 'not like', '%prueba%')
                                ->selectRaw('DAY(created_at) as dia, COUNT(*) as registros')
                                ->groupBy('dia')
                                ->orderBy('registros', 'desc')
                                ->first();

        $diaMenosRegistrosMesAnterior = User::whereMonth('created_at', $mesAnterior->month)
                                 ->whereYear('created_at', $mesAnterior->year)
                                 ->where('email', 'not like', '%sefarvzla%')
                                 ->where('email', 'not like', '%sefaruniversal%')
                                 ->where('name', 'not like', '%prueba%')
                                 ->selectRaw('DAY(created_at) as dia, COUNT(*) as registros')
                                 ->groupBy('dia')
                                 ->orderBy('registros', 'asc')
                                 ->first();

        // Promedio de registros en el mismo mes del año anterior
        $añoAnterior = $fechaActual->copy()->subYear();
        $promedioMismoMesAñoAnterior = User::whereMonth('created_at', $peticion['mes'])
                                            ->whereYear('created_at', $añoAnterior->year)
                                            ->where('email', 'not like', '%sefarvzla%')
                                            ->where('email', 'not like', '%sefaruniversal%')
                                            ->where('name', 'not like', '%prueba%')
                                            ->count() / $fechaActual->daysInMonth;

        $diaMasRegistrosMesActual_aa = User::whereMonth('created_at', $peticion['mes'])
                                ->whereYear('created_at', $añoAnterior->year)
                                ->where('email', 'not like', '%sefarvzla%')
                                ->where('email', 'not like', '%sefaruniversal%')
                                ->where('name', 'not like', '%prueba%')
                                ->selectRaw('DAY(created_at) as dia, COUNT(*) as registros')
                                ->groupBy('dia')
                                ->orderBy('registros', 'desc')
                                ->first();

        $diaMenosRegistrosMesActual_aa = User::whereMonth('created_at', $peticion['mes'])
                                ->whereYear('created_at', $añoAnterior->year)
                                ->where('email', 'not like', '%sefarvzla%')
                                ->where('email', 'not like', '%sefaruniversal%')
                                ->where('name', 'not like', '%prueba%')
                                ->selectRaw('DAY(created_at) as dia, COUNT(*) as registros')
                                ->groupBy('dia')
                                ->orderBy('registros', 'asc')
                                ->first();

        // Promedio de registros en el mes anterior al mes actual del año anterior
        $mesAnteriorAñoAnterior = $añoAnterior->subMonth();
        $promedioMesAnteriorAñoAnterior = User::whereMonth('created_at', $mesAnteriorAñoAnterior->month)
                                               ->whereYear('created_at', $mesAnteriorAñoAnterior->year)
                                               ->where('email', 'not like', '%sefarvzla%')
                                               ->where('email', 'not like', '%sefaruniversal%')
                                               ->where('name', 'not like', '%prueba%')
                                               ->count() / $mesAnteriorAñoAnterior->daysInMonth;

        $diaMasRegistrosMesAnterior_aa = User::whereMonth('created_at', $mesAnteriorAñoAnterior->month)
                                ->whereYear('created_at', $mesAnteriorAñoAnterior->year)
                                ->where('email', 'not like', '%sefarvzla%')
                                ->where('email', 'not like', '%sefaruniversal%')
                                ->where('name', 'not like', '%prueba%')
                                ->selectRaw('DAY(created_at) as dia, COUNT(*) as registros')
                                ->groupBy('dia')
                                ->orderBy('registros', 'desc')
                                ->first();

        $diaMenosRegistrosMesAnterior_aa = User::whereMonth('created_at', $mesAnteriorAñoAnterior->month)
                                ->whereYear('created_at', $mesAnteriorAñoAnterior->year)
                                ->where('email', 'not like', '%sefarvzla%')
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
                        ->whereYear('created_at', $peticion['año'])
    ->where('email', 'not like', '%sefarvzla%')
    ->where('email', 'not like', '%sefaruniversal%')
    ->where('name', 'not like', '%prueba%')
                        ->count()
            ],
            'mes_anterior' => [
                'promedio' => round($promedioMesAnterior, 2),
                'maximo' => $diaMasRegistrosMesAnterior->registros,
                'minimo' => $diaMenosRegistrosMesAnterior->registros,
                'total' => User::whereMonth('created_at', $peticion['mes'] == 1 ? 12 : $peticion['mes'] - 1)
                        ->whereYear('created_at', $peticion['año'])
                        ->where('email', 'not like', '%sefarvzla%')
                        ->where('email', 'not like', '%sefaruniversal%')
                        ->where('name', 'not like', '%prueba%')
                        ->count()
            ],
            'mes_actual_aa' => [
                'promedio' => round($promedioMismoMesAñoAnterior, 2),
                'maximo' => $diaMasRegistrosMesActual_aa->registros,
                'minimo' => $diaMenosRegistrosMesActual_aa->registros,
                'total' => User::whereMonth('created_at', $peticion['mes'])
                        ->whereYear('created_at', $añoAnterior->year)
    ->where('email', 'not like', '%sefarvzla%')
    ->where('email', 'not like', '%sefaruniversal%')
    ->where('name', 'not like', '%prueba%')
                        ->count()
            ],
            'mes_anterior_aa' => [
                'promedio' => round($promedioMesAnteriorAñoAnterior, 2),
                'maximo' => $diaMasRegistrosMesAnterior_aa->registros,
                'minimo' => $diaMenosRegistrosMesAnterior_aa->registros,
                'total' => User::whereMonth('created_at', $peticion['mes'] == 1 ? 12 : $peticion['mes'] - 1)
                        ->whereYear('created_at', $peticion['año']-1)
    ->where('email', 'not like', '%sefarvzla%')
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

        //$chartUrl = mostrarGraficoQuickChartwpd('https://quickchart.io/chart?c=' . urlencode(json_encode($chartConfig)));
        //$chartNight = 'https://quickchart.io/chart?c=' . urlencode(json_encode($chartConfignight));

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



        $pdf = PDF::loadView('reportes.plantilladiario2', compact(
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
            'usuariosPorServicio',
            'facturas'
        ));
        $pdfContent = $pdf->output();

        $emails = [
            'dpm.ladera@sefarvzla.com',
            'sistemasccs@sefarvzla.com',
            'crisantoantonio@gmail.com',
            'gflorez@sefarvzla.com',
            'practicanteit@sefarvzla.com',
            'cguerrero@sefarvzla.com',
            'automatizacion@sefarvzla.com',
            'admin.sefar@sefarvzla.com',
            'yeinsondiaz@sefarvzla.com'
        ];
        /*
        Mail::send('mail.reporte-diario', compact(
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
            'usuariosPorServicio'
        ), function ($message) use ($pdfContent, $peticion,$emails) {
            $message->to($emails)
                    ->subject('Reporte Diario - ' . $peticion["dia"] . '/' . $peticion["mes"] . '/' . $peticion["año"])
                    ->attachData($pdfContent, 'reporte_diario_' . $peticion["dia"] . '-' . $peticion["mes"] . '-' . $peticion["año"] . '.pdf', [
                        'mime' => 'application/pdf',
                    ]);
                });
        */

        // ===== Enviar PDF al servidor de WhatsApp =====
        try {
            // Convertir PDF a base64
            $pdfBase64 = base64_encode($pdfContent);
            $fileName = 'reporte_diario_' . $peticion["dia"] . '-' . $peticion["mes"] . '-' . $peticion["año"] . '.pdf';

            $numbers = ReportPhoneNumbers::all();

            $this->info('Enviando PDF por WhatsApp a ' . $numbers->count() . ' números.');

            $url = WhatsappBotURL::findOrFail(1) . '/send-file';

            foreach ($numbers as $number) {
                $this->info('Enviando a: ' . $number->phone_number);
                $this->info('Usando URL: ' . $url["url"] . '/send-file');

                $response = Http::timeout(120)->post($url["url"] . '/send-file', [
                    'number' => $number->phone_number,
                    'message' => "📊 *Reporte Diario*\n\nFecha: {$peticion['dia']}/{$peticion['mes']}/{$peticion['año']}",
                    'fileData' => $pdfBase64,
                    'fileName' => $fileName
                ]);
            }

            if ($response->successful()) {
                $this->info('PDF enviado por WhatsApp: ' . $response->json()['file']);
            } else {
                $this->error('Error: ' . $response->status() . ' - ' . $response->body());
            }

        } catch (\Exception $e) {
            $this->error('Error enviando PDF: ' . $e->getMessage());
        }

        $this->info('Reporte diario generado y enviado con éxito.');
    }
}

/*
function mostrarGraficoQuickChartwpd($chartUrl, $mimeType = 'image/png') {
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
*/
