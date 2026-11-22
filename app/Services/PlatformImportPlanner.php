<?php
namespace App\Services;

use Illuminate\Support\Facades\Schema;

/** Builds and queues local platform mirror imports without contacting providers. */
class PlatformImportPlanner
{
    public function __construct(
        private PlatformCatalog $catalog,
        private StripeAdminApi $stripe,
        private KalendiaApi $kalendia,
        private PaypalHistoryApi $paypal,
        private PlatformMirror $mirror,
    ) {}

    /** @return array<int, array{provider:string,account:string,configured:bool,mode:string,read_only:bool,resources:array}> */
    public function connections(): array
    {
        $connections = [];
        foreach ([['stripe','main'], ['stripe','spain_legacy'], ['kalendia','main']] as [$provider, $account]) {
            $configured = $provider === 'stripe' ? $this->stripe->configured($account) : $this->kalendia->configured();
            $mode = $provider === 'stripe' ? ($this->stripe->mode($account) === null ? 'unknown' : ($this->stripe->mode($account) ? 'live' : 'test')) : 'live';
            $connections[] = ['provider'=>$provider, 'account'=>$account, 'configured'=>$configured, 'mode'=>$mode,
                'read_only'=>PlatformIdentity::readOnly($provider, $account),
                'resources'=>array_keys($this->catalog->resources($provider)), 'strategy'=>'mirror'];
        }
        // PayPal history is intentionally fetched on demand by its existing API.
        $connections[] = ['provider'=>'paypal', 'account'=>'spain_legacy', 'configured'=>$this->paypal->configured(),
            'mode'=>'live', 'read_only'=>true, 'resources'=>[], 'strategy'=>'api', 'queueable'=>false, 'reason'=>'api_only'];
        return $connections;
    }

    public function plan(?string $provider = null, ?string $account = null): array
    {
        $connections = $this->filteredConnections($provider, $account);
        return ['connections'=>$connections, 'requested'=>0, 'already_running'=>0,
            'skipped'=>count($connections), 'reasons'=>$this->planReasons($connections)];
    }

    public function queueAll(?string $provider = null, ?string $account = null): array
    {
        $connections = $this->filteredConnections($provider, $account);
        $counts = ['requested'=>0, 'already_running'=>0, 'skipped'=>0, 'configured'=>0, 'reasons'=>[]];
        $needsMirror = collect($connections)->contains(fn ($connection) => $connection['strategy'] === 'mirror');
        $hasTables = !$needsMirror || collect(['platform_sync_states','platform_records','platform_events','platform_operations'])
            ->every(fn ($table) => Schema::hasTable($table));
        foreach ($connections as $connection) {
            if ($connection['provider'] === 'paypal') {
                $counts['skipped']++;
                $counts['reasons']['api_only'] = ($counts['reasons']['api_only'] ?? 0) + 1;
                continue;
            }
            if ($connection['configured']) $counts['configured']++;
            foreach ($connection['resources'] as $resource) {
                if (!$connection['configured']) {
                    $counts['skipped']++;
                    $counts['reasons']['not_configured'] = ($counts['reasons']['not_configured'] ?? 0) + 1;
                    continue;
                }
                if (!$hasTables) {
                    $counts['skipped']++;
                    $counts['reasons']['migration_missing'] = ($counts['reasons']['migration_missing'] ?? 0) + 1;
                    continue;
                }
                $queued = $this->mirror->queue($connection['provider'], $connection['account'], $resource);
                if ($queued) $counts['requested']++;
                else $counts['already_running']++;
            }
        }
        return $counts;
    }

    public function queueAccount(string $provider, string $account): array
    {
        return $this->queueAll($provider, $account);
    }

    private function filteredConnections(?string $provider, ?string $account): array
    {
        $connections = array_values(array_filter($this->connections(), fn ($row) =>
            ($provider === null || $row['provider'] === $provider) && ($account === null || $row['account'] === $account)));
        if (!$connections || ($provider !== null && !in_array($provider, ['stripe','kalendia','paypal'], true))
            || ($account !== null && !in_array($account, ['main','spain_legacy'], true))) {
            throw new \InvalidArgumentException('Unsupported provider or account filter.');
        }
        return $connections;
    }

    private function planReasons(array $connections): array
    {
        $reasons = [];
        foreach ($connections as $connection) {
            $reason = $connection['reason'] ?? (!$connection['configured'] ? 'not_configured' : null);
            if ($reason) $reasons[$reason] = ($reasons[$reason] ?? 0) + 1;
        }
        return $reasons;
    }
}
