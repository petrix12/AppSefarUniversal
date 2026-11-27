<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use App\Services\GoogleAuthenticationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthenticationController extends Controller
{
    private const REGISTRATION_CONTEXT_FIELDS = [
        'servicio',
        'cantidad_alzada',
        'antepasados',
        'vinculo_antepasados',
        'tiene_hermanos',
    ];

    public function __construct(private GoogleAuthenticationService $google) {}

    public function loginRedirect(Request $request): RedirectResponse
    {
        return $this->start($request, 'login');
    }

    public function redirect(Request $request): RedirectResponse
    {
        return $this->start($request, 'registration');
    }

    private function start(Request $request, string $flow): RedirectResponse
    {
        $request->session()->forget([GoogleAuthenticationService::REGISTRATION, GoogleAuthenticationService::PENDING_LINK, 'state']);
        $request->session()->put('google_oauth_flow', [
            'type' => $flow, 'context' => $flow === 'registration' ? $this->context($request) : [],
            'expires_at' => now()->addMinutes(30)->timestamp,
        ]);
        if (! $this->google->configured()) return $this->failure($request, 'El acceso con Google todavía no está configurado.');
        return Socialite::driver('google')->scopes(['openid', 'profile', 'email'])->with(['prompt' => 'select_account'])->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        try {
            $flow = $request->session()->get('google_oauth_flow');
            if (! $this->google->configured() || ! is_array($flow) || (int) ($flow['expires_at'] ?? 0) <= now()->timestamp) {
                return $this->failure($request, 'El acceso con Google venció o no está disponible. Inicia el acceso de nuevo.');
            }
            if ($request->filled('error')) return $this->failure($request, 'No se completó el acceso con Google. Puedes intentarlo de nuevo.');
            // Socialite validates and consumes the session state before exchanging the code.
            $profile = $this->google->profile(Socialite::driver('google')->user());
            $existingUser = $this->google->existingUser($profile);
            if ($existingUser) {
                if (! $existingUser->googleIdentity && ! $profile['authoritative_email']) {
                    $request->session()->forget('google_oauth_flow');
                    $request->session()->put(GoogleAuthenticationService::PENDING_LINK, $profile + ['user_id' => $existingUser->id]);
                    return redirect()->route('login')->withInput(['email' => $profile['email']])
                        ->with('status', 'Para vincular Google con tu cuenta, entra una vez con tu contraseña. Después podrás usar Google para acceder.');
                }
                $this->google->attach($existingUser, $profile);
                $request->session()->forget(['google_oauth_flow', GoogleAuthenticationService::REGISTRATION]);
                if (Features::enabled(Features::twoFactorAuthentication()) && $existingUser->two_factor_secret
                    && (! Fortify::confirmsTwoFactorAuthentication() || $existingUser->two_factor_confirmed_at)) {
                    $request->session()->regenerate();
                    $request->session()->put(['login.id' => $existingUser->id, 'login.remember' => false]);
                    TwoFactorAuthenticationChallenged::dispatch($existingUser);
                    return redirect()->route('two-factor.login');
                }
                Auth::login($existingUser);
                $request->session()->regenerate();
                return redirect()->intended(RouteServiceProvider::HOME);
            }
            $request->session()->forget('google_oauth_flow');
            $request->session()->put(GoogleAuthenticationService::REGISTRATION, $profile);
            return redirect()->route('register.v2.form', $flow['context'] ?? [])
                ->with('status', 'Verificamos tu cuenta de Google. Completa los datos restantes para finalizar tu registro.');
        } catch (ValidationException $exception) {
            return $this->failure($request, $exception->errors()['google'][0] ?? 'No se pudo verificar tu cuenta de Google.');
        } catch (\Throwable $exception) {
            Log::warning('Google authentication callback failed.', [
                'exception' => $exception::class,
            ]);

            return $this->failure($request, 'No pudimos completar el acceso con Google. Inténtalo de nuevo.');
        }
    }

    public function cancel(Request $request): RedirectResponse
    {
        $request->session()->forget(['google_oauth_flow', GoogleAuthenticationService::REGISTRATION, GoogleAuthenticationService::PENDING_LINK, 'state']);
        return redirect()->route('register.v2.form', $this->context($request));
    }

    private function failure(Request $request, string $message): RedirectResponse
    {
        $flow = $request->session()->pull('google_oauth_flow', []);
        $request->session()->forget([GoogleAuthenticationService::REGISTRATION, GoogleAuthenticationService::PENDING_LINK, 'state']);
        return redirect()->route(($flow['type'] ?? null) === 'registration' ? 'register.v2.form' : 'login', $flow['context'] ?? [])
            ->withErrors(['google' => $message]);
    }

    private function context(Request $request): array
    {
        return array_filter(Arr::only($request->query(), self::REGISTRATION_CONTEXT_FIELDS),
            fn ($value) => is_scalar($value) && (string) $value !== '' && strlen((string) $value) <= 255);
    }
}
