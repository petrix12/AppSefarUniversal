<?php

namespace App\Services\Mcp;

use RuntimeException;

class McpAuditLogger
{
    private int $sequence = 0;

    public function __construct(
        private string $path,
        private ?string $secret = null,
    ) {
        $this->ensureWritable();
    }

    public function append(string $event, array $data): void
    {
        $this->ensureWritable();

        $handle = fopen($this->path, 'c+b');

        if ($handle === false) {
            throw new RuntimeException('No se pudo abrir el archivo de auditoria MCP.');
        }

        try {
            if (! flock($handle, LOCK_EX)) {
                throw new RuntimeException('No se pudo bloquear el archivo de auditoria MCP.');
            }

            $record = array_merge([
                'schema_version' => 1,
                'timestamp' => now()->toIso8601String(),
                'event' => $event,
                'sequence' => ++$this->sequence,
                'prev_hash' => $this->lastHash($handle),
            ], $data);

            $payload = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            if ($payload === false) {
                throw new RuntimeException('No se pudo serializar el evento de auditoria MCP.');
            }

            $record['hash'] = $this->secret
                ? hash_hmac('sha256', $payload, $this->secret)
                : hash('sha256', $payload);

            $line = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            if ($line === false) {
                throw new RuntimeException('No se pudo serializar la linea de auditoria MCP.');
            }

            fseek($handle, 0, SEEK_END);

            if (fwrite($handle, $line . PHP_EOL) === false) {
                throw new RuntimeException('No se pudo escribir el evento de auditoria MCP.');
            }

            fflush($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }
    }

    public function sanitize(mixed $value, int $depth = 0): mixed
    {
        if ($depth > 6) {
            return '[DEPTH_LIMIT]';
        }

        if (is_array($value)) {
            $clean = [];
            $count = 0;

            foreach ($value as $key => $item) {
                $count++;

                if ($count > 50) {
                    $clean['_truncated'] = true;
                    break;
                }

                $keyString = is_int($key) ? (string) $key : $key;
                $clean[$key] = $this->isSensitiveKey($keyString)
                    ? '[REDACTED]'
                    : $this->sanitize($item, $depth + 1);
            }

            return $clean;
        }

        if (is_object($value)) {
            return $this->sanitize((array) $value, $depth + 1);
        }

        if (is_string($value)) {
            return strlen($value) > 1000 ? substr($value, 0, 1000) . '[TRUNCATED]' : $value;
        }

        if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
            return $value;
        }

        return gettype($value);
    }

    private function ensureWritable(): void
    {
        $directory = dirname($this->path);

        if (! is_dir($directory) && ! mkdir($directory, 0770, true) && ! is_dir($directory)) {
            throw new RuntimeException("No se pudo crear el directorio de auditoria MCP: {$directory}");
        }

        if (! is_writable($directory)) {
            throw new RuntimeException("El directorio de auditoria MCP no es escribible: {$directory}");
        }

        if (! file_exists($this->path) && file_put_contents($this->path, '', FILE_APPEND) === false) {
            throw new RuntimeException("No se pudo crear el archivo de auditoria MCP: {$this->path}");
        }

        if (! is_writable($this->path)) {
            throw new RuntimeException("El archivo de auditoria MCP no es escribible: {$this->path}");
        }
    }

    private function lastHash($handle): ?string
    {
        $stat = fstat($handle);
        $size = (int) ($stat['size'] ?? 0);

        if ($size <= 0) {
            return null;
        }

        $line = '';

        for ($position = $size - 1; $position >= 0; $position--) {
            fseek($handle, $position);
            $char = fgetc($handle);

            if ($char === false) {
                break;
            }

            if ($char === "\n" || $char === "\r") {
                if (trim($line) !== '') {
                    break;
                }

                continue;
            }

            $line = $char . $line;
        }

        $decoded = json_decode(trim($line), true);

        if (! is_array($decoded)) {
            return null;
        }

        return is_string($decoded['hash'] ?? null) ? $decoded['hash'] : null;
    }

    private function isSensitiveKey(string $key): bool
    {
        $lower = strtolower($key);

        foreach (['password', 'pass', 'token', 'secret', 'cookie', 'authorization', 'csrf', 'two_factor', '2fa', 'code'] as $needle) {
            if (str_contains($lower, $needle)) {
                return true;
            }
        }

        return false;
    }
}
