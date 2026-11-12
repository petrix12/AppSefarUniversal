<?php

namespace App\Support;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Request-local, opt-in measurements. Never stores SQL, bindings or CRM payloads. */
class CosPageTiming
{
    public const ATTRIBUTE = 'cos_page_timing';
    private Closure $clock;
    private float $startedAt;
    private float $checkpointAt;
    private int $queryCount = 0;
    private float $queryMs = 0;
    private int $checkpointQueries = 0;
    private float $checkpointQueryMs = 0;
    private array $stages = [];
    private array $external = [];
    private array $externalCalls = [];
    private array $httpTransfers = [];
    private ?float $totalMs = null;
    public bool $refreshPending = false;
    public readonly string $id;

    public function __construct(?Closure $clock = null)
    {
        $this->clock = $clock ?? static fn (): float => hrtime(true) / 1_000_000;
        $this->startedAt = $this->checkpointAt = ($this->clock)();
        $this->id = (string) Str::uuid();
    }

    public static function current(): ?self
    {
        $timing = app()->bound('request') ? request()->attributes->get(self::ATTRIBUTE) : null;
        return $timing instanceof self && $timing->totalMs === null ? $timing : null;
    }

    public static function authorized(Request $request): bool
    {
        $email = trim((string) config('cos_diagnostics.viewer_email'));
        $userId = config('cos_diagnostics.viewer_user_id');
        return $request->routeIs('crud.users.edit')
            && $email !== ''
            && strcasecmp((string) $request->user()?->email, $email) === 0
            && (blank($userId) || (string) $request->user()?->id === (string) $userId)
            && $request->user()?->hasRole('Administrador')
            && ! $request->user()->hasRole('Cliente');
    }

    public function query(float $milliseconds): void
    {
        if ($this->totalMs !== null) return;
        $this->queryCount++;
        $this->queryMs += max(0, $milliseconds);
    }

    public function checkpoint(string $name, string $label): void
    {
        if ($this->totalMs !== null) return;
        $now = ($this->clock)();
        $this->stages[] = [
            'name' => $name, 'label' => $label,
            'started_ms' => round(max(0, $this->checkpointAt - $this->startedAt), 2),
            'ended_ms' => round(max(0, $now - $this->startedAt), 2),
            'duration_ms' => round(max(0, $now - $this->checkpointAt), 2),
            'queries' => $this->queryCount - $this->checkpointQueries,
            'query_ms' => round($this->queryMs - $this->checkpointQueryMs, 2),
        ];
        $this->checkpointAt = $now;
        $this->checkpointQueries = $this->queryCount;
        $this->checkpointQueryMs = $this->queryMs;
    }

    /** External measurements overlap their parent stage and must not be added to it. */
    public static function external(string $name, string $label, Closure $operation): mixed
    {
        $timing = self::current();
        if (! $timing) return $operation();
        $started = ($timing->clock)();
        $failed = true;
        try {
            $result = $operation();
            $failed = false;
            return $result;
        } finally {
            $ended = ($timing->clock)();
            $duration = max(0, $ended - $started);
            $entry = $timing->external[$name] ?? ['name' => $name, 'label' => $label, 'duration_ms' => 0, 'calls' => 0, 'failures' => 0];
            $entry['duration_ms'] = round($entry['duration_ms'] + $duration, 2);
            $entry['calls']++;
            $entry['failures'] += (int) $failed;
            $timing->external[$name] = $entry;
            if (count($timing->externalCalls) < 100) {
                $timing->externalCalls[] = ['name' => $name, 'label' => $label,
                    'started_ms' => round(max(0, $started - $timing->startedAt), 2),
                    'duration_ms' => round($duration, 2), 'failed' => $failed];
            }
        }
    }

    /** cURL timing only: do not retain URLs, credentials, headers or response bodies. */
    public static function httpTransfer(string $service, \GuzzleHttp\TransferStats $stats): void
    {
        $timing = self::current();
        if (! $timing || count($timing->httpTransfers) >= 100) return;
        $details = $stats->getHandlerStats();
        $value = static fn (string $key): ?float => isset($details[$key]) && is_numeric($details[$key]) ? (float) $details[$key] * 1000 : null;
        $difference = static fn (?float $end, ?float $start): ?float => $end !== null && $start !== null ? round(max(0, $end - $start), 2) : null;
        $total = round(max(0, (float) $stats->getTransferTime() * 1000), 2);
        $dns = $value('namelookup_time');
        $tcp = $value('connect_time');
        $tls = $value('appconnect_time');
        $ready = $value('pretransfer_time');
        $firstByte = $value('starttransfer_time');
        $timing->httpTransfers[] = [
            'service' => $service, 'method' => $stats->getRequest()->getMethod(),
            'status' => $stats->getResponse()?->getStatusCode(),
            'started_ms' => round(max(0, ($timing->clock)() - $timing->startedAt - $total), 2),
            'duration_ms' => $total, 'dns_ms' => $dns === null ? null : round($dns, 2),
            'tcp_ms' => $difference($tcp, $dns),
            'tls_ms' => $tls !== null && $tls > 0 ? $difference($tls, $tcp) : ($tls === null ? null : 0),
            'ttfb_ms' => $firstByte === null ? null : round($firstByte, 2),
            'waiting_ms' => $difference($firstByte, $ready),
            'download_ms' => $difference($total, $firstByte),
        ];
    }

    public function finish(): array
    {
        if ($this->totalMs === null) {
            $this->checkpoint('response', 'Resto de la solicitud y auditoría');
            $this->totalMs = round(max(0, $this->checkpointAt - $this->startedAt), 2);
        }
        return [
            'trace_id' => $this->id, 'server_ms' => $this->totalMs,
            'queries' => $this->queryCount, 'query_ms' => round($this->queryMs, 2),
            'stages' => $this->stages, 'external' => array_values($this->external),
            'external_calls' => $this->externalCalls, 'http_transfers' => $this->httpTransfers,
            'refresh_pending' => $this->refreshPending,
        ];
    }

    public function serverTiming(): string
    {
        $report = $this->finish();
        $metrics = ['cos_total;dur='.$report['server_ms'], 'cos_db;dur='.$report['query_ms']];
        foreach ($report['stages'] as $stage) {
            $metrics[] = 'cos_'.$stage['name'].';dur='.$stage['duration_ms'];
        }
        return implode(', ', $metrics);
    }
}
