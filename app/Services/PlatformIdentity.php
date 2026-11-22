<?php
namespace App\Services;

class PlatformIdentity
{
    public static function supported(string $provider, string $account): bool
    {
        return in_array($provider, ['stripe', 'kalendia'], true)
            && ($account === 'main' || ($provider === 'stripe' && $account === 'spain_legacy'));
    }
    public static function readOnly(string $provider, string $account): bool
    {
        return $provider === 'stripe' && $account === 'spain_legacy';
    }
    public static function mode(string $provider,string $account): string
    {
        if (!self::supported($provider, $account)) throw new PlatformApiException('account_disabled');
        if ($provider==='kalendia') return 'live';
        return match(app(StripeAdminApi::class)->mode($account)) {true=>'live',false=>'test',default=>'unknown'};
    }
    public static function where(string $provider,string $account): array
    {
        return ['provider'=>$provider,'account'=>$account,'mode'=>self::mode($provider,$account)];
    }
}
