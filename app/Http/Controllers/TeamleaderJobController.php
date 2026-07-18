<?php

namespace App\Http\Controllers;

use App\Models\TlSyncLog;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class TeamleaderJobController extends Controller
{
    private array $queues = [
        'teamleader-sync',
        'teamleader-documents',
    ];

    public function index()
    {
        $now = time();

        $queueStats = collect($this->queues)
            ->map(fn (string $queue) => $this->queueStats($queue, $now));

        $totals = [
            'total' => $queueStats->sum('total'),
            'ready' => $queueStats->sum('ready'),
            'delayed' => $queueStats->sum('delayed'),
            'reserved' => $queueStats->sum('reserved'),
            'failed' => $this->failedJobsCount(),
        ];

        return view('teamleader.jobs.index', [
            'queueStats' => $queueStats,
            'totals' => $totals,
            'nextJobs' => $this->nextJobs($now),
            'failedJobs' => $this->recentFailedJobs(),
            'syncLogs' => $this->syncLogs(),
            'dataCounts' => $this->dataCounts(),
            'jobsTableExists' => Schema::hasTable('jobs'),
            'failedJobsTableExists' => Schema::hasTable('failed_jobs'),
        ]);
    }

    public function retryFailed(Request $request): RedirectResponse
    {
        return $this->retryFailedJobs();
    }

    public function retryFailedJob(int $failedJob): RedirectResponse
    {
        return $this->retryFailedJobs($failedJob);
    }

    public function clearFailed(Request $request): RedirectResponse
    {
        return $this->clearFailedJobs();
    }

    public function clearFailedJob(int $failedJob): RedirectResponse
    {
        return $this->clearFailedJobs($failedJob);
    }

    public function work(Request $request): RedirectResponse
    {
        $jobs = min(200, max(1, (int) $request->input('jobs', 20)));
        $timeout = min(60, max(10, (int) $request->input('timeout', 45)));

        $exitCode = Artisan::call('queue:work', [
            '--queue' => implode(',', $this->workerQueues()),
            '--stop-when-empty' => true,
            '--tries' => 3,
            '--timeout' => $timeout,
            '--max-jobs' => $jobs,
            '--no-interaction' => true,
        ]);

        $output = trim(Artisan::output());

        if ($exitCode !== 0) {
            return back()->with('error', "El worker termino con error. {$output}");
        }

        return back()->with('status', "Worker ejecutado. Jobs maximos: {$jobs}. " . ($output ?: 'Sin salida adicional.'));
    }

    private function workerQueues(): array
    {
        return (bool) config('services.teamleader.sync_documents', false)
            ? $this->queues
            : ['teamleader-sync'];
    }

    private function retryFailedJobs(?int $failedJobId = null): RedirectResponse
    {
        if (! Schema::hasTable('failed_jobs')) {
            return back()->with('error', 'No existe la tabla failed_jobs.');
        }

        $jobs = $this->failedJobsQuery()
            ->when($failedJobId, fn ($query) => $query->where('id', $failedJobId))
            ->get(['id', 'uuid']);

        if ($jobs->isEmpty()) {
            return back()->with('status', 'No hay jobs fallidos para reintentar.');
        }

        foreach ($jobs as $job) {
            Artisan::call('queue:retry', [
                'id' => [$job->uuid ?: (string) $job->id],
                '--no-interaction' => true,
            ]);
        }

        return back()->with(
            'status',
            "Se reintentaron {$jobs->count()} job(s). Laravel los retiro de failed_jobs y los puso nuevamente en cola."
        );
    }

    private function clearFailedJobs(?int $failedJobId = null): RedirectResponse
    {
        if (! Schema::hasTable('failed_jobs')) {
            return back()->with('error', 'No existe la tabla failed_jobs.');
        }

        $count = $this->failedJobsQuery()
            ->when($failedJobId, fn ($query) => $query->where('id', $failedJobId))
            ->delete();

        return back()->with('status', "Se limpiaron {$count} error(es) de jobs fallidos.");
    }

    private function queueStats(string $queue, int $now): array
    {
        if (!Schema::hasTable('jobs')) {
            return [
                'queue' => $queue,
                'total' => 0,
                'ready' => 0,
                'delayed' => 0,
                'reserved' => 0,
            ];
        }

        $base = DB::table('jobs')->where('queue', $queue);

        return [
            'queue' => $queue,
            'total' => (clone $base)->count(),
            'ready' => (clone $base)
                ->whereNull('reserved_at')
                ->where('available_at', '<=', $now)
                ->count(),
            'delayed' => (clone $base)
                ->whereNull('reserved_at')
                ->where('available_at', '>', $now)
                ->count(),
            'reserved' => (clone $base)
                ->whereNotNull('reserved_at')
                ->count(),
        ];
    }

    private function nextJobs(int $now): Collection
    {
        if (!Schema::hasTable('jobs')) {
            return collect();
        }

        return DB::table('jobs')
            ->whereIn('queue', $this->queues)
            ->orderBy('available_at')
            ->orderBy('id')
            ->limit(50)
            ->get()
            ->map(fn ($job) => $this->formatJob($job, $now));
    }

    private function recentFailedJobs(): Collection
    {
        if (!Schema::hasTable('failed_jobs')) {
            return collect();
        }

        return DB::table('failed_jobs')
            ->whereIn('queue', $this->queues)
            ->orderByDesc('failed_at')
            ->limit(25)
            ->get()
            ->map(function ($job) {
                $payload = $this->decodePayload($job->payload);
                $firstExceptionLine = strtok((string) $job->exception, "\n") ?: 'Sin detalle';

                return (object) [
                    'id' => $job->id,
                    'uuid' => $job->uuid ?? null,
                    'queue' => $job->queue,
                    'job_name' => $this->jobName($payload),
                    'summary' => $this->jobSummary($payload),
                    'error' => Str::limit($firstExceptionLine, 180),
                    'failed_at' => $job->failed_at,
                ];
            });
    }

    private function failedJobsQuery()
    {
        return DB::table('failed_jobs')->whereIn('queue', $this->queues);
    }

    private function failedJobsCount(): int
    {
        if (!Schema::hasTable('failed_jobs')) {
            return 0;
        }

        return DB::table('failed_jobs')
            ->whereIn('queue', $this->queues)
            ->count();
    }

    private function syncLogs(): Collection
    {
        if (!Schema::hasTable('tl_sync_logs')) {
            return collect();
        }

        return TlSyncLog::query()
            ->latest('started_at')
            ->latest('id')
            ->limit(12)
            ->get();
    }

    private function dataCounts(): array
    {
        return [
            'Contactos' => $this->countTable('tl_contacts'),
            'Empresas' => $this->countTable('tl_companies'),
            'Deals' => $this->countTable('tl_deals'),
            'Proyectos' => $this->countTable('tl_projects'),
            'Facturas' => $this->countTable('tl_invoices'),
            'Notas de credito' => $this->countTable('tl_credit_notes'),
            'Archivos TL' => $this->countTable('tl_documents'),
            'Archivos vinculados' => $this->countTable('tl_document_user_links'),
        ];
    }

    private function countTable(string $table): int
    {
        return Schema::hasTable($table) ? DB::table($table)->count() : 0;
    }

    private function formatJob(object $job, int $now): object
    {
        $payload = $this->decodePayload($job->payload);

        return (object) [
            'id' => $job->id,
            'queue' => $job->queue,
            'job_name' => $this->jobName($payload),
            'summary' => $this->jobSummary($payload),
            'attempts' => $job->attempts,
            'status' => $this->jobStatus($job, $now),
            'created_at' => $this->formatTimestamp($job->created_at),
            'available_at' => $this->formatTimestamp($job->available_at),
            'reserved_at' => $this->formatTimestamp($job->reserved_at),
        ];
    }

    private function jobStatus(object $job, int $now): string
    {
        if ($job->reserved_at !== null) {
            return 'Reservado';
        }

        return $job->available_at > $now ? 'Diferido' : 'Listo';
    }

    private function decodePayload(?string $payload): array
    {
        $decoded = json_decode($payload ?: '', true);

        return is_array($decoded) ? $decoded : [];
    }

    private function jobName(array $payload): string
    {
        $name = $payload['displayName']
            ?? data_get($payload, 'data.commandName')
            ?? 'Desconocido';

        return class_basename($name);
    }

    private function jobSummary(array $payload): string
    {
        $serializedCommand = data_get($payload, 'data.command');
        if (!$serializedCommand) {
            return 'Sin parametros';
        }

        $command = @unserialize($serializedCommand, ['allowed_classes' => false]);
        if (!$command) {
            return 'Sin parametros';
        }

        $allowed = [
            'page',
            'pageSize',
            'chunkIndex',
            'totalChunks',
            'logId',
            'entityType',
            'entityId',
        ];

        $parts = [];
        foreach ((array) $command as $key => $value) {
            $cleanKey = trim(str_replace("\0", '', (string) $key), '*');

            if (!in_array($cleanKey, $allowed, true)) {
                continue;
            }

            if (is_array($value)) {
                $value = count($value) . ' items';
            }

            $parts[] = "{$cleanKey}: {$value}";
        }

        return $parts ? implode(' / ', $parts) : 'Sin parametros';
    }

    private function formatTimestamp(?int $timestamp): string
    {
        if (!$timestamp) {
            return '-';
        }

        return Carbon::createFromTimestamp($timestamp)
            ->timezone(config('app.timezone'))
            ->format('d/m/Y H:i');
    }
}
