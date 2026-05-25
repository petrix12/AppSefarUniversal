<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class RunDailyTaskWorkflowForOwnerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const ALERT_EMAIL = 'sistemasccs@gmail.com';

    public int $tries = 1;
    public int $timeout = 1800;
    private string $capturedOutput = '';

    public function __construct(
        private int $advisorUserId,
        private string $advisorLabel,
        private array $options,
        private ?int $adminUserId = null,
        private bool $runListCleanup = false
    ) {
    }

    public function handle(): void
    {
        $output = '';
        $this->capturedOutput = '';

        try {
            if ($this->shouldForceReassign()) {
                $output .= $this->runCommand('tasks:daily-workflow', $this->forceOptions());
            }

            $output .= $this->runCommand('tasks:notify-unclosed', $this->notifyOptions());
            $output .= $this->runCommand('tasks:generate-daily', $this->generateOptions());

            if ($this->outputReportsProblems($output)) {
                $this->notifyFailure(
                    new RuntimeException('El job termino, pero reporto fallas de HubSpot o contactos omitidos.'),
                    $output,
                    false
                );
            }

            Log::channel('tasks')->info('Workflow diario de tareas por owner completado', [
                'admin_user_id' => $this->adminUserId,
                'advisor_user_id' => $this->advisorUserId,
                'advisor' => $this->advisorLabel,
                'options' => $this->options,
            ]);
        } catch (Throwable $e) {
            $this->notifyFailure($e, $this->capturedOutput ?: $output, true);

            throw $e;
        }
    }

    public function failed(Throwable $e): void
    {
        $this->notifyFailure($e, '', true);
    }

    private function runCommand(string $command, array $options): string
    {
        $exitCode = Artisan::call($command, $options);
        $commandOutput = Artisan::output();
        $entry = $this->formatCommandOutput($command, $options, $exitCode, $commandOutput);
        $this->capturedOutput .= $entry;

        Log::channel('tasks')->info('Comando de tareas ejecutado por owner', [
            'command' => $command,
            'exit_code' => $exitCode,
            'advisor_user_id' => $this->advisorUserId,
            'advisor' => $this->advisorLabel,
            'options' => $options,
            'output' => $commandOutput,
        ]);

        if ($exitCode !== 0) {
            throw new RuntimeException("{$command} termino con codigo {$exitCode}.");
        }

        return $entry;
    }

    private function forceOptions(): array
    {
        return array_filter([
            '--date' => $this->options['--date'] ?? null,
            '--per' => $this->options['--per'] ?? 10,
            '--advisor-id' => $this->advisorUserId,
            '--dry-run' => (bool) ($this->options['--dry-run'] ?? false),
            '--force-reassign' => true,
            '--force-limit' => $this->options['--force-limit'] ?? 200,
            '--skip-notify' => true,
            '--skip-generate' => true,
        ], fn ($value) => $value !== null && $value !== false);
    }

    private function notifyOptions(): array
    {
        return array_filter([
            '--date' => $this->options['--date'] ?? null,
            '--source-user-id' => $this->advisorUserId,
            '--dry-run' => (bool) ($this->options['--dry-run'] ?? false),
        ], fn ($value) => $value !== null && $value !== false);
    }

    private function generateOptions(): array
    {
        return array_filter([
            '--date' => $this->options['--date'] ?? null,
            '--per' => $this->options['--per'] ?? 10,
            '--advisor-id' => $this->advisorUserId,
            '--dry-run' => (bool) ($this->options['--dry-run'] ?? false),
            '--skip-list-cleanup' => ! $this->runListCleanup,
        ], fn ($value) => $value !== null && $value !== false);
    }

    private function shouldForceReassign(): bool
    {
        return (bool) ($this->options['--force-reassign'] ?? false)
            || (bool) ($this->options['--force'] ?? false);
    }

    private function outputReportsProblems(string $output): bool
    {
        $patterns = [
            '/Fallidos en HubSpot:\s*[1-9]\d*/i',
            '/Actualizaciones fallidas en HubSpot:\s*[1-9]\d*/i',
            '/Contactos omitidos por no poder sincronizar HubSpot:\s*[1-9]\d*/i',
            '/HubSpot fall/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $output) === 1) {
                return true;
            }
        }

        return false;
    }

    private function notifyFailure(Throwable $e, string $output, bool $failed): void
    {
        $key = 'tasks-owner-job-alert:' . md5($this->advisorUserId . '|' . json_encode($this->options) . '|' . $e->getMessage() . '|' . ($failed ? 'failed' : 'warning'));

        if (! Cache::add($key, true, now()->addDay())) {
            return;
        }

        $level = $failed ? 'error' : 'warning';
        Log::channel('tasks')->{$level}('Problema en workflow diario de tareas por owner', [
            'admin_user_id' => $this->adminUserId,
            'advisor_user_id' => $this->advisorUserId,
            'advisor' => $this->advisorLabel,
            'options' => $this->options,
            'error' => $e->getMessage(),
            'output' => $output,
        ]);

        $subject = $failed
            ? "[App Sefar] Fallo job tareas owner {$this->advisorUserId}"
            : "[App Sefar] Alerta job tareas owner {$this->advisorUserId}";

        $body = implode("\n", [
            $failed ? 'Fallo un job de tareas por HubSpot owner.' : 'Un job de tareas termino con advertencias.',
            '',
            "Asesor: {$this->advisorLabel}",
            "User ID: {$this->advisorUserId}",
            'Admin que lo lanzo: ' . ($this->adminUserId ?: 'N/A'),
            'Fecha: ' . now()->toDateTimeString(),
            '',
            'Error:',
            get_class($e) . ': ' . $e->getMessage(),
            '',
            'Opciones:',
            json_encode($this->options, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            '',
            'Salida capturada:',
            $this->trimOutput($output),
        ]);

        try {
            Mail::raw($body, function ($message) use ($subject) {
                $message->to(self::ALERT_EMAIL)->subject($subject);
            });
        } catch (Throwable $mailError) {
            Log::channel('tasks')->error('No se pudo enviar alerta de fallo del workflow diario de tareas', [
                'recipient' => self::ALERT_EMAIL,
                'advisor_user_id' => $this->advisorUserId,
                'mail_error' => $mailError->getMessage(),
                'original_error' => $e->getMessage(),
            ]);
        }
    }

    private function formatCommandOutput(string $command, array $options, int $exitCode, string $output): string
    {
        return implode("\n", [
            '',
            "### php artisan {$command}",
            'Exit code: ' . $exitCode,
            'Options: ' . json_encode($options, JSON_UNESCAPED_SLASHES),
            trim($output) !== '' ? trim($output) : '(sin salida)',
            '',
        ]);
    }

    private function trimOutput(string $output): string
    {
        $output = trim($output);

        if ($output === '') {
            return '(sin salida capturada; si fue timeout puede haberse detenido antes de escribir).';
        }

        $limit = 30000;

        if (strlen($output) <= $limit) {
            return $output;
        }

        return '[salida recortada a los ultimos 30000 caracteres]' . "\n" . substr($output, -$limit);
    }
}
