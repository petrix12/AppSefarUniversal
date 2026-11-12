<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Factura;
use App\Models\Agcliente;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\ConnectionException;
use App\Models\MondayBoard;
use App\Models\MondayItemValue;

class MondayController extends Controller
{
    /*INFO DE MONDAY*/

    public function mondayreportes()
    {
        //Tablas que NO voy a llamar
        $analisis = [
            "878831315",
            "625187241",
            "3469085450",
            "708123651",
            "669590637",
            "1845706367",
            "1845701215",
            "1845710504",
            "708128239",
            "3950637564",
            "1162066037",
            "2213224176"
        ];

        $etiquetadovsefar = "765394861";

        $stats = [];
        $eventas = [];

        $stats = $this->statusCountsForBoards($analisis);
        $eventas = $this->statusCountsForBoards([$etiquetadovsefar]);

        return view('crud.monday.stats', compact('stats', 'eventas'));

    }

    private function statusCountsForBoards(array $boardIds): array
    {
        if (! Schema::hasTable('monday_boards') || ! Schema::hasTable('monday_item_values')) {
            return [];
        }

        $names = MondayBoard::query()->whereIn('id', $boardIds)->pluck('name', 'id');

        return MondayItemValue::query()
            ->select(['board_id', 'text', DB::raw('COUNT(*) as total')])
            ->whereIn('board_id', $boardIds)
            ->where('monday_column_id', 'status')
            ->whereNotNull('text')
            ->whereHas('item')
            ->groupBy('board_id', 'text')
            ->get()
            ->reduce(function (array $result, MondayItemValue $value) use ($names): array {
                $boardName = (string) ($names[$value->board_id] ?? $value->board_id);
                $result[$boardName][(string) $value->text] = (int) $value->total;

                return $result;
            }, []);
    }

    public function mondayregistrar()
    {
        return view('crud.monday.registrar');
    }

    public function validarMD(Request $request)
    {

    }

    public function registrarMD(Request $request)
    {
        $validated = $request->validate([
            'passport' => ['required', 'string', 'max:20'],
        ], [
            'passport.required' => 'Ingresa el número de pasaporte del cliente.',
            'passport.max' => 'El pasaporte no puede tener más de 20 caracteres.',
        ]);
        $passport = trim($validated['passport']);
        $user = User::query()->where('passport', $passport)->first();

        if (! $user) {
            return redirect()->route('mondayregistrar')->with('status', 'error');
        }

        if ((string) $user->pay !== '2') {
            return redirect()->route('mondayregistrar')->with('status', 'error2');
        }

        $invoice = Factura::query()->where('id_cliente', $user->id)->latest('id')->first();
        $services = $invoice?->compras()->pluck('servicio_hs_id')->filter()->unique()->implode(', ');
        $services = $services ?: $user->servicio;

        $people = Agcliente::query()
            ->where('IDCliente', $passport)
            ->whereIn('IDPersona', [1, 2, 3])
            ->get()
            ->keyBy('IDPersona');
        $father = $people->get(2);
        $mother = $people->get(3);
        $link = rtrim(config('app.url'), '/').'/tree/'.rawurlencode($passport);
        $columns = [
            'texto' => $passport,
            'texto_largo8' => trim(($father?->Nombres ?? '').' '.($father?->Apellidos ?? '')),
            'texto_largo75' => trim(($mother?->Nombres ?? '').' '.($mother?->Apellidos ?? '')),
            'enlace' => ['url' => $link, 'text' => $link],
            'estado54' => 'Arbol Incompleto',
            'texto1' => $services ?: '',
            'texto4' => $user->hs_id ?: '',
        ];
        $birthDate = $this->registrationBirthDate($people->get(1), $user);
        if ($birthDate !== null) {
            $columns['fecha75'] = ['date' => $birthDate];
        }

        $token = config('services.monday.token');
        if (blank($token)) {
            Log::warning('Registro manual en Monday sin token configurado', ['user_id' => $user->id]);

            return redirect()->route('mondayregistrar')->with('status', 'error3');
        }

        $query = <<<'GRAPHQL'
            mutation ($myItemName: String!, $columnVals: JSON!) {
                create_item (
                    board_id: 878831315,
                    group_id: "duplicate_of_en_proceso",
                    item_name: $myItemName,
                    column_values: $columnVals
                ) { id }
            }
            GRAPHQL;

        try {
            $response = Http::withHeaders(['Authorization' => $token])
                ->connectTimeout(10)
                ->timeout(30)
                ->post('https://api.monday.com/v2', [
                    'query' => $query,
                    'variables' => [
                        'myItemName' => trim(($user->apellidos ?? '').' '.($user->nombres ?? '')) ?: $user->name,
                        'columnVals' => json_encode($columns, JSON_UNESCAPED_UNICODE),
                    ],
                ]);
            $itemId = $response->json('data.create_item.id');

            if (! $response->successful() || $response->json('errors')
                || ! is_scalar($itemId) || blank((string) $itemId)) {
                Log::warning('Monday rechazó el registro manual del cliente', [
                    'user_id' => $user->id,
                    'status' => $response->status(),
                    'errors' => $response->json('errors'),
                ]);

                return redirect()->route('mondayregistrar')->with('status', 'error3');
            }
        } catch (ConnectionException $exception) {
            Log::warning('No se pudo conectar con Monday para el registro manual', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);

            return redirect()->route('mondayregistrar')->with('status', 'error3');
        }

        return redirect()->route('mondayregistrar')->with('status', 'ok');
    }

    private function registrationBirthDate(?Agcliente $person, User $user): ?string
    {
        $year = (int) $person?->AnhoNac;
        $month = (int) $person?->MesNac;
        $day = (int) $person?->DiaNac;
        if (checkdate($month, $day, $year)) {
            return sprintf('%04d-%02d-%02d', $year, $month, $day);
        }

        $date = substr((string) $user->date_of_birth, 0, 10);
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts)
            && checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            return $date;
        }

        // Partial or absent dates must never become an invented 1970-01-01.
        return null;
    }
}
