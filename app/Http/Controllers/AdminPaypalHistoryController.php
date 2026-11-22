<?php

namespace App\Http\Controllers;

use App\Services\PaypalHistoryApi;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdminPaypalHistoryController extends Controller
{
    public function listing(Request $request, PaypalHistoryApi $paypal)
    {
        $user = $request->user();
        abort_unless($user && $user->hasRole('Administrador') && ! $user->hasRole('Cliente'), 403);
        abort_unless($user->can('paypal.history'), 403);

        $start = $request->query('start_date', CarbonImmutable::now('UTC')->subDays(29)->toDateString());
        $end = $request->query('end_date', CarbonImmutable::now('UTC')->toDateString());
        $transactionId = $request->query('transaction_id');
        $errors = [];
        $result = null;

        if ($request->hasAny(['start_date', 'end_date', 'transaction_id', 'page'])) {
            $validated = $request->validate([
                'start_date' => ['required', 'date_format:Y-m-d'],
                'end_date' => ['required', 'date_format:Y-m-d'],
                'transaction_id' => ['nullable', 'regex:/^[A-Za-z0-9]{17,19}$/'],
                'page' => ['sometimes', 'integer', 'min:1', 'max:2147483647'],
            ]);

            $from = CarbonImmutable::createFromFormat('!Y-m-d', $validated['start_date'], 'UTC');
            $through = CarbonImmutable::createFromFormat('!Y-m-d', $validated['end_date'], 'UTC');
            $today = CarbonImmutable::now('UTC')->startOfDay();
            $earliest = $today->subYears(3);

            if ($through->lt($from)) {
                throw ValidationException::withMessages(['end_date' => 'La fecha final debe ser igual o posterior a la inicial.']);
            }
            if ($from->diffInDays($through) > 30) {
                throw ValidationException::withMessages(['end_date' => 'El rango máximo es de 31 días.']);
            }
            if ($from->lt($earliest) || $through->gt($today)) {
                throw ValidationException::withMessages(['start_date' => 'PayPal permite consultar hasta los últimos tres años y no fechas futuras.']);
            }

            if (! $paypal->configured()) {
                $errors[] = 'Las credenciales de consulta histórica aún no están configuradas.';
            } else {
                $result = $paypal->transactions(
                    $from->format('Y-m-d\T00:00:00\Z'),
                    $through->endOfDay()->min(CarbonImmutable::now('UTC'))->format('Y-m-d\TH:i:s\Z'),
                    (int) ($validated['page'] ?? 1),
                    $validated['transaction_id'] ?? null,
                );
                if (! $result['ok']) {
                    $errors[] = $result['error'];
                }
            }
        }

        return response()->view('paypal-history.index', [
            'startDate' => $start,
            'endDate' => $end,
            'transactionId' => $transactionId,
            'result' => $result,
            'apiErrors' => $errors,
            'configured' => $paypal->configured(),
        ])->header('Cache-Control', 'private, no-store');
    }
}
