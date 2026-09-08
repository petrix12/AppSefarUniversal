<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthenticationController extends Controller
{
    private const REGISTRATION_CONTEXT_FIELDS = [
        'servicio',
        'pay',
        'rol',
        'cantidad_alzada',
        'antepasados',
        'vinculo_antepasados',
    ];

    public function redirect(Request $request): RedirectResponse
    {
        if (! $this->isGoogleConfigured()) {
            return $this->returnToRegistration($request, 'El acceso con Google todavía no está configurado.');
        }

        $context = array_filter(
            Arr::only($request->query(), self::REGISTRATION_CONTEXT_FIELDS),
            fn ($value) => $value !== null && $value !== ''
        );
        $request->session()->put('register_v2_google_context', $context);

        return Socialite::driver('google')
            ->scopes(['openid', 'profile', 'email'])
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        if (! $this->isGoogleConfigured()) {
            return $this->returnToRegistration($request, 'El acceso con Google todavía no está configurado.');
        }

        try {
            $googleUser = Socialite::driver('google')->user();
            $rawProfile = $googleUser->getRaw();
            $email = mb_strtolower(trim((string) $googleUser->getEmail()));
            $emailVerified = data_get($rawProfile, 'email_verified', data_get($rawProfile, 'verified_email'));

            if ($email === '') {
                return $this->returnToRegistration($request, 'Google no proporcionó un correo electrónico para continuar.');
            }

            if ($emailVerified !== null && ! filter_var($emailVerified, FILTER_VALIDATE_BOOLEAN)) {
                return $this->returnToRegistration($request, 'Confirma tu correo en Google antes de continuar.');
            }

            $existingUser = User::query()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->first();

            if ($existingUser) {
                Auth::login($existingUser);
                $request->session()->regenerate();

                return redirect()->intended(RouteServiceProvider::HOME);
            }

            $context = $request->session()->pull('register_v2_google_context', []);
            $fullName = trim((string) $googleUser->getName());
            $firstName = trim((string) (data_get($rawProfile, 'given_name') ?: Str::before($fullName, ' ')));
            $lastName = trim((string) (data_get($rawProfile, 'family_name') ?: Str::after($fullName, ' ')));

            return redirect()
                ->route('register.v2.form', $context)
                ->with('google_registration', [
                    'nombres' => $firstName,
                    'apellidos' => $lastName,
                    'email' => $email,
                ])
                ->with('status', 'Verificamos tu cuenta de Google. Completa los datos restantes para finalizar tu registro.');
        } catch (\Throwable $exception) {
            Log::warning('Google authentication callback failed.', [
                'exception' => $exception::class,
            ]);

            return $this->returnToRegistration($request, 'No pudimos completar el acceso con Google. Inténtalo de nuevo.');
        }
    }

    private function isGoogleConfigured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect'));
    }

    private function returnToRegistration(Request $request, string $message): RedirectResponse
    {
        $context = $request->session()->pull('register_v2_google_context', []);

        return redirect()
            ->route('register.v2.form', $context)
            ->withErrors(['google' => $message]);
    }
}
