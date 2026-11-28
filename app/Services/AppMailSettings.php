<?php

namespace App\Services;

use App\Models\AppMailLog;
use App\Models\Setting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AppMailSettings
{
    public function ready(): bool
    {
        return Schema::hasTable('settings') && Schema::hasTable('app_mail_logs') && Schema::hasTable('app_mail_events');
    }

    public function originalMailer(): string
    {
        $name = (string) config('app_mail.original_mailer', 'smtp');
        return $name !== '' && $name !== 'app-mail' ? $name : 'smtp';
    }

    public function provider(): string
    {
        // No database access during application boot. Old deployments continue
        // using their existing provider until these migrations have run.
        if (! Schema::hasTable('settings')) return 'default';
        return Setting::get('app_mail.provider') === 'ses' ? 'ses' : 'default';
    }

    public function ses(): array
    {
        return (array) config('app_mail.ses', []);
    }

    public function sesReady(): bool
    {
        $ses = $this->ses();
        return filled($ses['key'] ?? null) && filled($ses['secret'] ?? null)
            && (bool) filter_var($ses['from_email'] ?? '', FILTER_VALIDATE_EMAIL)
            && (bool) preg_match('/^[a-z]{2}(?:-[a-z]+)+-\d$/D', (string) ($ses['region'] ?? ''))
            && (blank($ses['reply_to'] ?? null) || (bool) filter_var($ses['reply_to'], FILTER_VALIDATE_EMAIL));
    }

    public function fingerprint(): string
    {
        // Only a digest is saved; keys, passwords and message bodies never are.
        return hash('sha256', json_encode($this->ses(), JSON_THROW_ON_ERROR));
    }

    public function tested(): ?array
    {
        if (! $this->ready()) return null;
        $proof = json_decode((string) Setting::get('app_mail.ses_test', ''), true);
        if (! is_array($proof) || ! hash_equals($this->fingerprint(), (string) ($proof['fingerprint'] ?? ''))
            || (int) ($proof['expires_at'] ?? 0) <= now()->timestamp) return null;
        $log = AppMailLog::find($proof['log_id'] ?? 0);
        return $log && $log->provider === 'ses' && $log->is_test && in_array($log->status, ['accepted', 'delivered'], true)
            ? $proof : null;
    }

    public function recordTest(AppMailLog $log): void
    {
        Setting::set('app_mail.ses_test', json_encode([
            'log_id' => $log->id, 'recipient' => $log->recipients[0] ?? '',
            'fingerprint' => $this->fingerprint(), 'expires_at' => now()->addDay()->timestamp,
        ], JSON_THROW_ON_ERROR));
    }

    public function activate(string $provider, int $actorId): void
    {
        if (! in_array($provider, ['default', 'ses'], true)) throw ValidationException::withMessages(['provider' => 'Proveedor no válido.']);
        if (! $this->ready()) throw ValidationException::withMessages(['provider' => 'Primero aplica la migración de correo de la app.']);
        if ($provider === 'ses' && (! $this->sesReady() || ! $this->tested())) {
            throw ValidationException::withMessages(['provider' => 'Envía una prueba con la configuración actual de Amazon SES antes de activarlo.']);
        }
        Setting::set('app_mail.provider', $provider);
        Setting::set('app_mail.updated_by', (string) $actorId);
    }
}
