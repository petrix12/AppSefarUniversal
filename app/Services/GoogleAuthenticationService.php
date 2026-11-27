<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Contracts\User as SocialUser;

class GoogleAuthenticationService
{
    public const REGISTRATION = 'google_registration';
    public const PENDING_LINK = 'google_pending_link';

    public function configured(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect'));
    }

    public function profile(SocialUser $user): array
    {
        $raw = $user->getRaw();
        $email = Str::lower(trim((string) $user->getEmail()));
        $subject = $user->getId();
        if (! is_string($subject) || ! preg_match('/^[\x21-\x7e]{1,255}$/D', $subject) || ! filter_var($email, FILTER_VALIDATE_EMAIL)
            || ! filter_var(data_get($raw, 'email_verified', data_get($raw, 'verified_email')), FILTER_VALIDATE_BOOLEAN)) {
            throw ValidationException::withMessages(['google' => 'Google debe proporcionar una cuenta con correo verificado para continuar.']);
        }
        $name = preg_split('/\s+/', trim((string) $user->getName()), 2);
        return [
            'subject' => $subject, 'email' => $email,
            'nombres' => trim((string) (data_get($raw, 'given_name') ?: ($name[0] ?? ''))),
            'apellidos' => trim((string) (data_get($raw, 'family_name') ?: ($name[1] ?? ''))),
            'authoritative_email' => Str::endsWith($email, '@gmail.com') || (is_string(data_get($raw, 'hd')) && filled(data_get($raw, 'hd'))),
            'expires_at' => now()->addMinutes(30)->timestamp,
        ];
    }

    public function existingUser(array $profile): ?User
    {
        $linked = User::where('google_id', $profile['subject'])->first();
        if ($linked) {
            if (! hash_equals((string) $linked->google_id, $profile['subject'])) $this->conflict();
            return $linked;
        }
        $users = User::whereRaw('LOWER(email) = ?', [$profile['email']])->limit(2)->get();
        if ($users->count() > 1) $this->conflict();
        $user = $users->first();
        if ($user?->google_id && ! hash_equals((string) $user->google_id, $profile['subject'])) $this->conflict();
        return $user;
    }

    public function attach(User $user, array $profile): void
    {
        DB::transaction(function () use ($user, $profile) {
            $owner = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if (($owner->google_id && ! hash_equals((string) $owner->google_id, $profile['subject']))
                || (! $owner->google_id && Str::lower($owner->email) !== $profile['email'])
                || User::where('google_id', $profile['subject'])->whereKeyNot($owner->id)->exists()) $this->conflict();
            $values = ['google_id' => $profile['subject']];
            if (! $owner->email_verified_at && Str::lower($owner->email) === $profile['email']) $values['email_verified_at'] = now();
            $owner->forceFill($values)->save();
            $user->forceFill($values);
        });
    }

    public function pendingRegistration(Request $request): ?array
    {
        $profile = $request->session()->get(self::REGISTRATION);
        if (! is_array($profile) || empty($profile['subject']) || empty($profile['email'])
            || (int) ($profile['expires_at'] ?? 0) <= now()->timestamp) {
            $request->session()->forget(self::REGISTRATION);
            return null;
        }
        return $profile;
    }

    public function registrationForSubmission(Request $request): ?array
    {
        $profile = $this->pendingRegistration($request);
        if (! $profile && $request->boolean('google_registration')) {
            throw ValidationException::withMessages(['google' => 'La verificación de Google venció. Pulsa «Continuar con Google» de nuevo.']);
        }
        if ($profile && Str::lower(trim((string) $request->input('email'))) !== $profile['email']) {
            throw ValidationException::withMessages(['email' => 'Usa el correo verificado con Google o elige «Usar otro correo».']);
        }
        return $profile;
    }

    public function completePendingLink(User $user, Request $request): void
    {
        if (! $request->hasSession()) return;
        $pending = $request->session()->pull(self::PENDING_LINK);
        if (! is_array($pending) || (int) ($pending['user_id'] ?? 0) !== (int) $user->id
            || (int) ($pending['expires_at'] ?? 0) <= now()->timestamp
            || Str::lower($user->email) !== ($pending['email'] ?? null)) return;
        try {
            $this->attach($user, $pending);
            $request->session()->flash('status', 'Tu cuenta de Google quedó vinculada. Ya puedes usarla para entrar.');
        } catch (\Throwable $exception) {
            Log::warning('No se pudo vincular Google después del acceso.', ['user_id' => $user->id, 'exception' => $exception::class]);
        }
    }

    private function conflict(): never
    {
        throw ValidationException::withMessages(['google' => 'Esta cuenta no coincide con la cuenta de Google vinculada. Entra con tu contraseña o contacta con soporte.']);
    }
}
