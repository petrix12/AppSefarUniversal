<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\Teamleader\TeamleaderFocusProvider;
use App\Models\TeamleaderToken;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;

class TeamleaderController extends Controller
{
    protected $provider;

    public function __construct()
    {
        $this->provider = new TeamleaderFocusProvider([
            'clientId'     => env('TEAMLEADER_CLIENT_ID'),
            'clientSecret' => env('TEAMLEADER_CLIENT_SECRET'),
            'redirectUri'  => env('TEAMLEADER_REDIRECT_URI'),
        ]);
    }

    public function redirectToProvider()
    {
        $authorizationUrl = $this->provider->getAuthorizationUrl();
        session(['oauth2state' => $this->provider->getState()]);

        return redirect($authorizationUrl);
    }

    public function handleProviderCallback(Request $request)
    {
        // Verificar el parámetro state
        $state = $request->input('state');
        if (empty($state) || $state !== session('oauth2state')) {
            session()->forget('oauth2state');
            return redirect()->route('teamleader.redirect')->withErrors('Estado inválido.');
        }

        // Obtener el token de acceso
        try {
            $accessToken = $this->provider->getAccessToken('authorization_code', [
                'code' => $request->input('code'),
            ]);

            // Almacenar los tokens de forma segura
            $this->storeTokens($accessToken);

            return redirect()->route('teamleader.success');

        } catch (\Exception $e) {
            return redirect()->route('teamleader.redirect')->withErrors('Error al obtener el token de acceso: ' . $e->getMessage());
        }
    }

    // Método para almacenar los tokens
    private function storeTokens($accessToken)
    {
        TeamleaderToken::updateOrCreate(
            ['id' => 1], // Asumimos que solo habrá un registro
            [
                'access_token' => Crypt::encryptString($accessToken->getToken()),
                'refresh_token' => Crypt::encryptString($accessToken->getRefreshToken()),
                'expires' => $accessToken->getExpires(),
            ]
        );
    }

    // Método para obtener un token de acceso válido
    private function getAccessToken()
    {
        $tokenRecord = TeamleaderToken::find(1);

        if (!$tokenRecord) {
            throw new \Exception('No se encontraron tokens. Por favor, autentica primero.');
        }

        if (time() >= $tokenRecord->expires) {
            // El token ha expirado, renovarlo
            $refreshToken = Crypt::decryptString($tokenRecord->refresh_token);
            $newAccessToken = $this->provider->getAccessToken('refresh_token', [
                'refresh_token' => $refreshToken,
            ]);

            // Almacenar los nuevos tokens
            $this->storeTokens($newAccessToken);

            return $newAccessToken->getToken();
        } else {
            // El token es válido
            return Crypt::decryptString($tokenRecord->access_token);
        }
    }



    // Método de ejemplo para realizar una petición GET a Teamleader
    public function getContacts()
    {
        try {
            $accessToken = $this->getAccessToken();

            $response = Http::withToken($accessToken)
                ->post('https://api.focus.teamleader.eu/contacts.list', [
                    'page' => [
                        'size' => 10,
                    ],
                ]);

            if ($response->successful()) {
                $contacts = $response->json();
                // Procesar los contactos
                return response()->json($contacts);
            } else {
                return response()->json(['error' => 'Error al obtener los contactos'], $response->status());
            }

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    // Ruta de éxito después de la autenticación inicial
    public function success()
    {
        return '¡Integración con Teamleader configurada exitosamente!';
    }
}
