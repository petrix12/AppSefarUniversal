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

    public function createContact($user)
    {
        try {
            $accessToken = $this->getAccessToken();

            $response = Http::withToken($accessToken)
                ->post('https://api.focus.teamleader.eu/contacts.add', [
                    'first_name' => $user->nombres,
                    'last_name' => $user->apellidos,
                    'emails' => [
                        [
                            'type' => 'primary',
                            'email' => $user->email,
                        ],
                    ],
                    'telephones' => [
                        [
                            'type' => 'mobile',
                            'number' => $user->phone,
                        ],
                    ],
                ]);

            if ($response->successful()) {
                $contactData = $response->json();
                return $contactData['data'];
            } else {
                $error = $response->json();
                $errorMessage = isset($error['errors'][0]['title']) ? $error['errors'][0]['title'] : 'Error desconocido';
                throw new \Exception('Error al crear el contacto: ' . $errorMessage);
            }
        } catch (\Exception $e) {
            throw new \Exception('Error: ' . $e->getMessage());
        }
    }

    public function getDealsByContactId($contactId)
    {
        try {
            $accessToken = $this->getAccessToken();

            $response = Http::withToken($accessToken)
                ->post('https://api.focus.teamleader.eu/deals.list', [
                    'filter' => [
                        'contact_id' => $contactId,
                    ],
                    'page' => [
                        'size' => 100, // Ajusta según el límite de registros que desees traer
                    ],
                ]);

            if ($response->successful()) {
                $deals = $response->json();

                if (!empty($deals['data'])) {
                    // Obtener campos personalizados de cada negocio
                    foreach ($deals['data'] as &$deal) {
                        $dealDetails = $this->getDealDetails($deal['id']);
                        $deal['custom_fields'] = $dealDetails['custom_fields'] ?? [];
                    }
                    return $deals['data'];
                } else {
                    return [];
                }
            } else {
                $error = $response->json();
                $errorMessage = isset($error['errors'][0]['title']) ? $error['errors'][0]['title'] : 'Error desconocido';
                throw new \Exception('Error al obtener las negociaciones: ' . $errorMessage);
            }
        } catch (\Exception $e) {
            throw new \Exception('Error: ' . $e->getMessage());
        }
    }

    private function getDealDetails($dealId)
    {
        try {
            $accessToken = $this->getAccessToken();

            $response = Http::withToken($accessToken)
                ->post('https://api.focus.teamleader.eu/deals.info', [
                    'id' => $dealId,
                ]);

            if ($response->successful()) {
                return $response->json()['data'];
            } else {
                $error = $response->json();
                $errorMessage = isset($error['errors'][0]['title']) ? $error['errors'][0]['title'] : 'Error desconocido';
                throw new \Exception('Error al obtener los detalles del negocio: ' . $errorMessage);
            }
        } catch (\Exception $e) {
            throw new \Exception('Error: ' . $e->getMessage());
        }
    }

    public function getModuleByFieldId(string $tlId): ?string
    {
        // 1. Obtener el token de acceso
        $accessToken = $this->getAccessToken();

        // 2. Construir el payload de la petición POST
        //    (basado en tu snippet con cURL)
        $payload = [
            "filter" => [
                "ids" => [
                    $tlId
                ]
            ],
            "page" => [
                "size" => 20,
                "number" => 1
            ],
            "sort" => [
                [
                    "field" => "label"
                ]
            ]
        ];

        // 3. Realizar la llamada con Http Client de Laravel
        $response = Http::withToken($accessToken)
            ->withHeaders([
                'Content-Type' => 'application/json',
                // A veces también se requiere 'Accept' => 'application/json'
            ])
            ->post('https://api.focus.teamleader.eu/customFieldDefinitions.list', $payload);

        // 4. Verificar si la petición fue exitosa
        if ($response->successful()) {
            $data = $response->json();

            // (Opcional) Depura para ver la estructura real
            // dd($data);

            // Supongamos que la respuesta tiene un array "data"
            // y en su primer elemento se define la llave "module"
            if (!empty($data['data'][0]['context'])) {
                return $data['data'][0]['context'];
            } else {
                return null;
            }
        }

        // 5. Manejo de errores
        //    Podrías usar dd($response->json()) para ver el detalle
        $error = $response->json();
        throw new \Exception("Error al obtener el módulo del campo: " . json_encode($error));
    }

}
