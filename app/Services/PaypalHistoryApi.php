<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Arr;

/** Read-only client for the legacy Spanish PayPal account's transaction history. */
class PaypalHistoryApi
{
    private const API_BASE = 'https://api-m.paypal.com';

    public function configured(): bool
    {
        return filled(config('services.paypal.history_es_client_id'))
            && filled(config('services.paypal.history_es_secret'));
    }

    /** @return array{ok:bool, transactions?:array, page?:int, total_pages?:int, error?:string} */
    public function transactions(string $start, string $end, int $page = 1, ?string $transactionId = null): array
    {
        if (! $this->configured()) {
            return ['ok' => false, 'error' => 'Credenciales históricas no configuradas.'];
        }

        try {
            $tokenResponse = Http::withBasicAuth(
                (string) config('services.paypal.history_es_client_id'),
                (string) config('services.paypal.history_es_secret'),
            )->asForm()->acceptJson()->connectTimeout(4)->timeout(15)->withOptions(['allow_redirects' => false])
                ->post(self::API_BASE.'/v1/oauth2/token', ['grant_type' => 'client_credentials']);

            if (! $tokenResponse->successful() || ! is_string($tokenResponse->json('access_token'))) {
                return ['ok' => false, 'error' => 'PayPal no autorizó la consulta histórica.'];
            }

            $query = [
                'start_date' => $start,
                'end_date' => $end,
                'fields' => 'transaction_info,payer_info',
                'page_size' => 100,
                'page' => $page,
            ];
            if ($transactionId !== null && $transactionId !== '') {
                $query['transaction_id'] = $transactionId;
            }

            $response = Http::withToken($tokenResponse->json('access_token'))->acceptJson()
                ->connectTimeout(4)->timeout(20)->withOptions(['allow_redirects' => false])
                ->get(self::API_BASE.'/v1/reporting/transactions', $query);

            $transactions = $response->json('transaction_details');
            if (! $response->successful() || !is_array($transactions) || count($transactions) > 100) {
                return ['ok' => false, 'error' => 'PayPal no pudo devolver las transacciones solicitadas.'];
            }

            return [
                'ok' => true,
                'transactions' => collect($transactions)->filter(fn($row)=>is_array($row))->map(function ($row) {
                    $info = Arr::only(is_array($row['transaction_info'] ?? null) ? $row['transaction_info'] : [],
                        ['transaction_id','transaction_initiation_date','transaction_status','transaction_subject','transaction_note']);
                    $info['transaction_amount'] = Arr::only(is_array(data_get($row,'transaction_info.transaction_amount'))
                        ? $row['transaction_info']['transaction_amount'] : [], ['value','currency_code']);
                    return ['transaction_info'=>$info,'payer_info'=>[
                        'email_address'=>is_string(data_get($row,'payer_info.email_address')) ? $row['payer_info']['email_address'] : null,
                        'payer_name'=>Arr::only(is_array(data_get($row,'payer_info.payer_name')) ? $row['payer_info']['payer_name'] : [],
                            ['given_name','surname','alternate_full_name','full_name']),
                    ]];
                })->values()->all(),
                'page' => (int) $response->json('page', $page),
                'total_pages' => max(1, (int) $response->json('total_pages', 1)),
            ];
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'No fue posible conectar con PayPal.'];
        }
    }
}
