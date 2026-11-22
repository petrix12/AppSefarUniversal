<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;

class StripeAdminApi
{
    public function key(string $account): string
    {
        return match ($account) {
            'main' => (string) config('services.stripe.secret'),
            'spain_legacy' => (string) config('services.stripe.history_es_secret'),
            default => throw new PlatformApiException('account_disabled'),
        };
    }
    public function configured(string $account): bool { return $this->key($account) !== ''; }
    public function mode(string $account): ?bool
    {
        $key = $this->key($account);
        if (preg_match('/^(sk|rk)_(live|test)_/', $key, $m)) return $m[2] === 'live';
        return null;
    }
    public function request(string $account, string $method, string $path, array $data = [], ?string $key = null): array
    {
        if ($account === 'spain_legacy' && $method !== 'GET') throw new PlatformApiException('read_only_account', 403);
        if (!$this->configured($account)) throw new PlatformApiException('not_configured');
        if (!preg_match('~^(account|balance|charges|payment_intents|customers|invoices|refunds|subscriptions|disputes|products|prices|webhook_endpoints)(/[A-Za-z0-9_]+){0,2}$~D', $path)
            || !in_array($method, ['GET','POST'], true)) throw new PlatformApiException('invalid_operation');
        try {
            $request = Http::withToken($this->key($account))->acceptJson()->asForm()->connectTimeout(4)->timeout(20)->withoutRedirecting();
            if ($key) $request = $request->withHeaders(['Idempotency-Key'=>$key]);
            $response = $method === 'GET' ? $request->get('https://api.stripe.com/v1/'.$path, $data)
                : $request->post('https://api.stripe.com/v1/'.$path, $data);
        } catch (\Throwable) { throw new PlatformApiException(); }
        if (!$response->successful() || !is_array($response->json())) {
            $code = $response->json('error.code');
            throw new PlatformApiException(is_string($code) && preg_match('/^[a-z_]{1,70}$/D', $code) ? $code : 'http_'.$response->status(), $response->status());
        }
        $result = $response->json();
        if (isset($result['livemode']) && $this->mode($account) !== null && $result['livemode'] !== $this->mode($account)) {
            throw new PlatformApiException('mode_mismatch');
        }
        return $result;
    }
}
