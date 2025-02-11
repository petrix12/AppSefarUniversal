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

    public function listProjectsByCustomerId(string $customerId)
    {
        try {
            $accessToken = $this->getAccessToken();

            // Payload de la solicitud
            $payload = [
                'filter' => [
                    'customer' => [
                        'type' => 'contact', // Cambia a 'company' si es una empresa
                        'id' => $customerId
                    ],
                ],
                'page' => [
                    'size' => 100, // Tamaño máximo por página
                    'number' => 1,
                ],// Datos adicionales si los necesitas
            ];

            // Realizar la solicitud
            $response = Http::withToken($accessToken)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->post('https://api.focus.teamleader.eu/projects.list', $payload);

            // Validar la respuesta
            if ($response->successful()) {
                $data = $response->json();
                return $data['data'] ?? []; // Retorna los proyectos listados
            } else {
                // Manejo de errores
                $error = $response->json();
                $errorMessage = $error['errors'][0]['title'] ?? 'Error desconocido';
                throw new \Exception('Error al listar proyectos: ' . $errorMessage);
            }
        } catch (\Exception $e) {
            throw new \Exception('Error: ' . $e->getMessage());
        }
    }


    public function getProjectDetails(string $projectId)
    {
        try {
            $accessToken = $this->getAccessToken();

            $response = Http::withToken($accessToken)
                ->post('https://api.focus.teamleader.eu/projects.info', [
                    'id' => $projectId, // Incluye datos adicionales si los necesitas
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['data']; // Retorna los detalles del proyecto
            } else {
                $error = $response->json();
                $errorMessage = $error['errors'][0]['title'] ?? 'Error desconocido';
                throw new \Exception('Error al obtener detalles del proyecto: ' . $errorMessage);
            }
        } catch (\Exception $e) {
            throw new \Exception('Error: ' . $e->getMessage());
        }
    }

    public function getUserIdByEmail($email)
    {
        // Llama al endpoint users.list
        $response = Http::withToken($this->getAccessToken())
            ->post('https://api.focus.teamleader.eu/users.list', [
                'page' => [
                    'size' => 100, // Ajusta el tamaño según la cantidad de usuarios
                    'number' => 1,
                ],
            ]);

        if ($response->successful()) {
            $users = $response->json()['data'];

            // Buscar el usuario por correo
            foreach ($users as $user) {
                if ($user['email'] === $email) {
                    return $user['id'];
                }
            }

            throw new \Exception("No se encontró un usuario con el correo: $email");
        }

        throw new \Exception('Error al obtener la lista de usuarios.');
    }


    public function createProjectFromHubspotDeal($hubspotDeal, $customerId, $camposDeTeamleader)
    {
        try {
            $responsibleUserId = $this->getUserIdByEmail('seguridad@sefarvzla.com');

            $participants = [
                [
                    "participant" => [
                        'id' => $this->getUserIdByEmail('sistemasccs@sefarvzla.com'),
                        'type' => 'user',
                    ],
                    "role" => "decision_maker"
                ],
                [
                    "participant" => [
                        'id' => $this->getUserIdByEmail('cmolina@sefarvzla.com'),
                        'type' => 'user',
                    ],
                    "role" => "decision_maker"
                ],
                [
                    "participant" => [
                        'id' => $this->getUserIdByEmail('asistentedeproduccion@sefarvzla.com'),
                        'type' => 'user',
                    ],
                    "role" => "decision_maker"
                ],
                [
                    "participant" => [
                        'id' => $this->getUserIdByEmail('asistentepresidencial@sefarvzla.com'),
                        'type' => 'user',
                    ],
                    "role" => "decision_maker"
                ],
                [
                    "participant" => [
                        'id' => $this->getUserIdByEmail('milenacera@sefarvzla.com'),
                        'type' => 'user',
                    ],
                    "role" => "decision_maker"
                ],
            ];

            $accessToken = $this->getAccessToken();

            // Mapear los campos de HS al formato de TL
            $customFields = [];
            $dealprops = $hubspotDeal['properties'];

            foreach ($dealprops as $hsfield => $value) {
                if(isset($camposDeTeamleader["$hsfield"]) && isset($value)){
                    $customFields[] = [
                        'id' => $camposDeTeamleader["$hsfield"],
                        'value' => $value,
                    ];
                }
            }

            $startDate = null;
            $dueDate = null;

            if (!empty($hubspotDeal['properties']['createdate'])) {
                $startDateObj = new \DateTime($hubspotDeal['properties']['createdate']);
                $startDate = $startDateObj->format('Y-m-d');

                // Calcular fechas para los hitos
                $milestoneStartDate = $startDateObj->modify('+1 day')->format('Y-m-d');
                $dueDate = $startDateObj->modify('+1 year')->format('Y-m-d');
            }

            // Crear hitos válidos
            $milestones = [
                [
                    'name' => 'Inicio del Proyecto', // Cambia según el contexto
                    'starts_on' => $milestoneStartDate,
                    'due_on' => $dueDate,
                    'responsible_user_id' => $responsibleUserId, // Propietario
                ],
            ];

            $amount = $hubspotDeal['properties']['amount'] ?? 0;

            if (!is_numeric($amount)) {
                $amount = (float) str_replace([',', '.'], '', $amount); // Limpia y convierte a número
            }

            // Payload para crear el proyecto en TL
            $payload = [
                'title' => $hubspotDeal['properties']['dealname'] ?? 'Sin título',
                'customer' => [
                    'type' => 'contact',
                    'id' => $customerId,
                ],
                'participants' => $participants,            // Participantes
                'milestones' => $milestones, // Agregar hitos válidos
                'budget' => [
                    'amount' => (float)$amount,
                    'currency' => "EUR"
                ],
                'starts_on' => $startDate,
                'due_on' => $dueDate,
                'custom_fields' => $customFields,
            ];

            // Realizar la solicitud a la API de Teamleader
            $response = Http::withToken($accessToken)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post('https://api.focus.teamleader.eu/projects.create', $payload);

            // Validar la respuesta
            if ($response->successful()) {
                return $response->json();
            } else {
                $error = $response->json();
                $errorMessage = $error['errors'][0]['title'] ?? 'Error desconocido';
                throw new \Exception('Error al crear el proyecto en Teamleader: ' . $errorMessage);
            }
        } catch (\Exception $e) {
            throw new \Exception('Error: ' . $e->getMessage());
        }
    }


    public function getProjectsWithDetailsByCustomerId(string $customerId)
    {
        try {
            // Listar proyectos del cliente
            $projects = $this->listProjectsByCustomerId($customerId);

            // Obtener detalles de cada proyecto
            $detailedProjects = [];
            foreach ($projects as $project) {
                $details = $this->getProjectDetails($project['id']);
                $detailedProjects[] = array_merge($project, $details); // Combina datos básicos y detalles
            }

            return $detailedProjects;
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

    public function updateProject($projectId, $updatedData)
    {
            $accessToken = $this->getAccessToken();

            // Construir el payload con el ID del proyecto y los datos a actualizar
            $payload = array_merge(['id' => $projectId], $updatedData);

            $response = Http::withToken($accessToken)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post('https://api.focus.teamleader.eu/projects.update', $payload);

            if ($response->successful()) {
                return $response->json();
            } else {
                $error = $response->json();
                $errorMessage = $error['errors'][0]['title'] ?? 'Error desconocido';
                throw new \Exception('Error al actualizar el proyecto: ' . $errorMessage);
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
