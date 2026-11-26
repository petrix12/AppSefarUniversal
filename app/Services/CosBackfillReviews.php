<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;

/** Private, append-only review records for the initial local COS calculation. */
class CosBackfillReviews
{
    private const LOG_ENTRY_PATTERN = '/^\[([^\]]+)\]\s+\S+\.(?:EMERGENCY|ALERT|CRITICAL|ERROR|WARNING|NOTICE|INFO|DEBUG): COS: revisión pendiente durante la carga inicial de HubSpot\.\s+(\{[^{}\r\n]*\})(?:\s+\[\])?\s*$/';

    /** @return list<array{user_id: int, reason: string, recorded_at: string}> */
    public function forRun(array $state): array
    {
        $startedAt = $state['started_at'] ?? null;
        $lastId = max(0, (int) ($state['last_id'] ?? 0));
        if (! is_string($startedAt) || $lastId === 0) {
            return [];
        }

        $rows = [];
        foreach ($this->readRunRows($startedAt) as $row) {
            if ($row['user_id'] <= $lastId) {
                $rows[$row['user_id']] = $row;
            }
        }
        ksort($rows);

        return array_values($rows);
    }

    public function record(array $state, int $userId, string $reason): void
    {
        if ($userId < 1 || blank($state['started_at'] ?? null) || ! $this->validReason($reason)) {
            return;
        }

        $this->appendRows([[
                'run_started_at' => $state['started_at'],
                'user_id' => $userId,
                'reason' => $reason,
                'recorded_at' => now()->toIso8601String(),
            ]]);
    }

    /** Read only the exact, historical review log entries for this run. */
    public function recoverHistorical(array $state): int
    {
        $startedAt = $state['started_at'] ?? null;
        if (! is_string($startedAt) || $startedAt === '') {
            return 0;
        }
        $started = CarbonImmutable::parse($startedAt);
        $lastId = max(0, (int) ($state['last_id'] ?? 0));
        $maxId = max(0, (int) ($state['max_id'] ?? $lastId));
        $newRows = [];

        foreach ($this->laravelLogPaths() as $logPath) {
            if (File::lastModified($logPath) < $started->getTimestamp()) {
                continue;
            }
            $handle = @fopen($logPath, 'rb');
            if ($handle === false) {
                continue;
            }
            try {
                while (($line = fgets($handle)) !== false) {
                    $entry = $this->parseLogEntry($line, $started, $lastId, $maxId);
                    if ($entry !== null) {
                        $previous = $newRows[$entry['user_id']] ?? null;
                        if ($previous === null || CarbonImmutable::parse($entry['recorded_at'])
                            ->lt(CarbonImmutable::parse($previous['recorded_at']))) {
                            $newRows[$entry['user_id']] = $entry;
                        }
                    }
                }
            } finally {
                fclose($handle);
            }
        }

        $existing = [];
        foreach ($this->readRunRows($startedAt) as $row) {
            $existing[$row['user_id']] = $row['recorded_at'];
        }
        $entriesToAppend = [];
        $newUsers = [];
        foreach ($newRows as $entry) {
            if (! isset($existing[$entry['user_id']])) {
                $newUsers[$entry['user_id']] = true;
            } elseif (CarbonImmutable::parse($entry['recorded_at'])
                ->greaterThanOrEqualTo(CarbonImmutable::parse($existing[$entry['user_id']]))) {
                continue;
            }
            $entriesToAppend[] = [
                'run_started_at' => $startedAt,
                'user_id' => $entry['user_id'],
                'reason' => $entry['reason'],
                'recorded_at' => $entry['recorded_at'],
            ];
            $existing[$entry['user_id']] = $entry['recorded_at'];
        }
        $this->appendRows($entriesToAppend);

        return count($newUsers);
    }

    private function readRunRows(string $startedAt): array
    {
        $path = $this->path();
        if (! File::exists($path)) {
            return [];
        }
        $rows = [];
        foreach (File::lines($path) as $line) {
            $row = json_decode($line, true);
            if (! is_array($row) || ($row['run_started_at'] ?? null) !== $startedAt) {
                continue;
            }
            $userId = filter_var($row['user_id'] ?? null, FILTER_VALIDATE_INT);
            $reason = $row['reason'] ?? null;
            $recordedAt = $row['recorded_at'] ?? null;
            if ($userId !== false && $userId > 0 && is_string($reason) && $this->validReason($reason)
                && is_string($recordedAt)) {
                $candidate = ['user_id' => $userId, 'reason' => $reason, 'recorded_at' => $recordedAt];
                if (! isset($rows[$userId]) || CarbonImmutable::parse($recordedAt)
                    ->lt(CarbonImmutable::parse($rows[$userId]['recorded_at']))) {
                    $rows[$userId] = $candidate;
                }
            }
        }

        return array_values($rows);
    }

    private function appendRows(array $entries): void
    {
        if ($entries === []) {
            return;
        }

        $path = $this->path();
        File::ensureDirectoryExists(dirname($path));
        $handle = fopen($path, 'ab');
        if ($handle === false) {
            throw new \RuntimeException('No se pudo abrir el registro privado de revisiones COS.');
        }
        try {
            if (! flock($handle, LOCK_EX)) {
                throw new \RuntimeException('No se pudo bloquear el registro privado de revisiones COS.');
            }
            foreach ($entries as $entry) {
                if (fwrite($handle, json_encode($entry, JSON_THROW_ON_ERROR)."\n") === false) {
                    throw new \RuntimeException('No se pudo guardar el caso de revisión COS.');
                }
            }
            fflush($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function parseLogEntry(string $line, CarbonImmutable $startedAt, int $lastId, int $maxId): ?array
    {
        if (! preg_match(self::LOG_ENTRY_PATTERN, trim($line), $matches)) {
            return null;
        }
        try {
            $recordedAt = CarbonImmutable::parse($matches[1], config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
        if ($recordedAt->lt($startedAt)) {
            return null;
        }
        $data = json_decode($matches[2], true);
        if (! is_array($data)) {
            return null;
        }
        $userId = filter_var($data['user_id'] ?? null, FILTER_VALIDATE_INT);
        $reason = $data['reason'] ?? null;
        if ($userId === false || $userId < 1 || $userId > $lastId || ($maxId > 0 && $userId > $maxId)
            || ! is_string($reason) || ! $this->validReason($reason)) {
            return null;
        }

        return ['user_id' => $userId, 'reason' => $reason, 'recorded_at' => $recordedAt->toIso8601String()];
    }

    private function laravelLogPaths(): array
    {
        $singlePath = config('logging.channels.single.path', storage_path('logs/laravel.log'));
        $dailyPath = config('logging.channels.daily.path', storage_path('logs/laravel.log'));
        $paths = [$singlePath];
        $directory = dirname($dailyPath);
        $base = basename($dailyPath, '.log');
        $paths = array_merge($paths, glob($directory.'/'.$base.'-????-??-??.log') ?: []);

        return array_values(array_unique(array_filter($paths, fn ($path) => File::exists($path))));
    }

    private function validReason(string $reason): bool
    {
        return preg_match('/^[a-z][a-z0-9_]{0,39}$/', $reason) === 1;
    }

    private function path(): string
    {
        return CosPortfolioBackfill::progressPath().'.reviews.ndjson';
    }
}
