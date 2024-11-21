<?php

namespace App\Services;

use App\Models\TeamleaderToken;
use App\Services\Teamleader\TeamleaderFocusProvider;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

class TeamleaderService
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

    /**
     * Obtener un token de acceso válido.
     */
    public function getAccessToken()
    {
        $tokenRecord = TeamleaderToken::find(1);

        if (!$tokenRecord) {
            throw new \Exception('No se encontraron tokens. Por favor, autentica primero con Teamleader.');
        }

        // Verificar si el token ha expirado
        if (time() >= $tokenRecord->expires) {
            // El token ha expirado, renovar usando el refresh_token
            $refreshToken = Crypt::decryptString($tokenRecord->refresh_token);

            try {
                $newAccessToken = $this->provider->getAccessToken('refresh_token', [
                    'refresh_token' => $refreshToken,
                ]);

                // Almacenar los nuevos tokens
                $this->storeTokens($newAccessToken);

                return $newAccessToken->getToken();

            } catch (\Exception $e) {
                throw new \Exception('No se pudo renovar el token de acceso: ' . $e->getMessage());
            }
        } else {
            // El token es válido, devolverlo
            return Crypt::decryptString($tokenRecord->access_token);
        }
    }

    /**
     * Almacenar los tokens en la base de datos.
     */
    private function storeTokens($accessToken)
    {
        TeamleaderToken::updateOrCreate(
            ['id' => 1],
            [
                'access_token' => Crypt::encryptString($accessToken->getToken()),
                'refresh_token' => Crypt::encryptString($accessToken->getRefreshToken()),
                'expires' => $accessToken->getExpires(),
            ]
        );
    }

    /**
     * Buscar un contacto por correo electrónico.
     */
    public function searchContactByEmail($email)
    {
        try {
            $accessToken = $this->getAccessToken();

            $response = Http::withToken($accessToken)
                ->post('https://api.focus.teamleader.eu/contacts.list', [
                    'filter' => [
                        'email' => [
                            'type' => 'primary',
                            'email' => $email,
                        ],
                    ],
                    'page' => [
                        'size' => 1,
                    ],
                ]);

            if ($response->successful()) {
                $contacts = $response->json();
                if (!empty($contacts['data'])) {
                    // Retornar el primer contacto encontrado
                    return $contacts['data'][0];
                } else {
                    // No se encontraron contactos
                    return null;
                }
            } else {
                // Manejar errores de la API
                $error = $response->json();
                $errorMessage = isset($error['errors'][0]['title']) ? $error['errors'][0]['title'] : 'Error desconocido';
                throw new \Exception('Error al buscar el contacto: ' . $errorMessage);
            }
        } catch (\Exception $e) {
            // Manejar excepciones generales
            throw new \Exception('Error: ' . $e->getMessage());
        }
    }

    public function getContactById($id)
    {
        try {
            $accessToken = $this->getAccessToken();

            $response = Http::withToken($accessToken)
                ->post('https://api.focus.teamleader.eu/contacts.info', [
                    'id' => $id,
                ]);

            if ($response->successful()) {
                $contactData = $response->json();
                return $contactData['data'];
            } else {
                // Manejar errores de la API
                $error = $response->json();
                $errorMessage = isset($error['errors'][0]['title']) ? $error['errors'][0]['title'] : 'Error desconocido';
                throw new \Exception('Error al obtener el contacto: ' . $errorMessage);
            }
        } catch (\Exception $e) {
            // Manejar excepciones generales
            throw new \Exception('Error: ' . $e->getMessage());
        }
    }

}
