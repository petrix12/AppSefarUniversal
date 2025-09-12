<?php

namespace App\Services;

use App\Models\TeamleaderToken;
use App\Services\Teamleader\TeamleaderFocusProvider;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Promise;

class TeamleaderService
{
    protected $provider;

    private $customFieldId = '624a9810-53dc-0770-965b-65891c631673';

    public function __construct()
    {
        $this->provider = new TeamleaderFocusProvider([
            'clientId'     => env('TEAMLEADER_CLIENT_ID'),
            'clientSecret' => env('TEAMLEADER_CLIENT_SECRET'),
            'redirectUri'  => env('TEAMLEADER_REDIRECT_URI'),
        ]);
    }

    public function executeConcurrent(array $callbacks)
    {
        $promises = [];

        foreach ($callbacks as $key => $callback) {
            $promises[$key] = $callback();
        }

        $results = Promise\Utils::settle($promises)->wait();

        $output = [];
        foreach ($results as $key => $result) {
            $output[$key] = $result['state'] === 'fulfilled' ? $result['value'] : null;
        }

        return $output;
    }

    /**
     * Versión con promesa de getContactById
     */
    public function getContactByIdPromise($id)
    {
        return Promise\Create::promiseFor($this->getContactById($id));
    }

    /**
     * Versión con promesa de listProjectsByCustomerId
     */
    public function listProjectsByCustomerIdPromise($customerId)
    {
        return Promise\Create::promiseFor($this->listProjectsByCustomerId($customerId));
    }

    /**
     * Versión con promesa de getProjectDetails
     */
    public function getProjectDetailsPromise($projectId)
    {
        return Promise\Create::promiseFor($this->getProjectDetails($projectId));
    }

    /**
     * Versión optimizada con promesas de getProjectsWithDetailsByCustomerId
     */
    public function getProjectsWithDetailsByCustomerIdPromise($customerId)
    {
        return Promise\Create::promiseFor(function() use ($customerId) {
            // Obtener proyectos y detalles en paralelo
            $results = $this->executeConcurrent([
                'projects' => fn() => $this->listProjectsByCustomerId($customerId),
            ]);

            $projects = $results['projects'] ?? [];

            // Crear promesas para los detalles de cada proyecto
            $detailPromises = [];
            foreach ($projects as $project) {
                $detailPromises['project_'.$project['id']] =
                    $this->getProjectDetailsPromise($project['id']);
            }

            // Esperar por todos los detalles
            $details = $this->executeConcurrent($detailPromises);

            // Combinar resultados
            $detailedProjects = [];
            foreach ($projects as $project) {
                $projectId = $project['id'];
                $detailKey = 'project_'.$projectId;
                if (isset($details[$detailKey])) {
                    $detailedProjects[] = array_merge($project, $details[$detailKey]);
                }
            }

            return $detailedProjects;
        });
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

    private $customFieldDefinitionsCache = [];

    public function getCustomFieldDefinitions(string $context = 'project'): array
    {
        if (!empty($this->customFieldDefinitionsCache[$context])) {
            return $this->customFieldDefinitionsCache[$context];
        }

        $accessToken = $this->getAccessToken();
        $pageNumber = 1;
        $pageSize = 100;
        $allFields = [];

        do {
            $payload = [
                'filter' => [
                    'context' => $context,
                ],
                'page' => [
                    'size' => $pageSize,
                    'number' => $pageNumber,
                ],
            ];

            $response = Http::withToken($accessToken)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post('https://api.focus.teamleader.eu/customFieldDefinitions.list', $payload);

            if ($response->successful()) {
                $data = $response->json();
                $fields = $data['data'] ?? [];
                $allFields = array_merge($allFields, $fields);

                $total = $data['meta']['page']['total'] ?? 0;
                $hasMorePages = $pageNumber * $pageSize < $total;
                $pageNumber++;
            } else {
                $error = $response->json();
                $errorMessage = $error['errors'][0]['title'] ?? 'Error desconocido';
                throw new \Exception("Error al obtener definiciones de campos: " . $errorMessage);
            }
        } while ($hasMorePages);

        // cachearlo
        $this->customFieldDefinitionsCache[$context] = $allFields;

        return $allFields;
    }

    public function getCustomFieldLabel(string $id, string $context = 'project'): ?string
    {
        $fields = $this->getCustomFieldDefinitions($context);

        foreach ($fields as $field) {
            if ($field['id'] === $id) {
                return $field['label'];
            }
        }

        return null;
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


    /**
     * Buscar un contacto por número de pasaporte en Teamleader.
     */
    public function searchContactByPassport($passportNumber)
    {
        try {
            $accessToken = $this->getAccessToken();

            $response = Http::withToken($accessToken)
                ->post('https://api.focus.teamleader.eu/contacts.list', [
                    'filter' => [
                        'custom_fields' => [
                            [
                                'id' => '624a9810-53dc-0770-965b-65891c631673',
                                'value' => $passportNumber
                            ]
                        ]
                    ],
                    'page' => [
                        'size' => 1,
                    ]
                ]);

            if ($response->successful()) {
                $contacts = $response->json();
                if (!empty($contacts['data'])) {
                    $contact = $contacts['data'][0];

                    $email = null;
                    if (!empty($contact['emails'])) {
                        foreach ($contact['emails'] as $emailData) {
                            if ($emailData['type'] === 'primary') {
                                $email = $emailData['email'];
                                break;
                            }
                        }
                    }

                    return [
                        'id' => $contact['id'],
                        'properties' => [
                            'email' => $email,
                            'firstname' => $contact['first_name'] ?? null,
                            'lastname' => $contact['last_name'] ?? null
                        ]
                    ];
                }
                return null;
            } else {
                $error = $response->json();
                throw new \Exception('Teamleader API error: '.($error['errors'][0]['title'] ?? 'Unknown error'));
            }
        } catch (\Exception $e) {
            throw new \Exception('Failed to search contact by passport: '.$e->getMessage());
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

    /**
     * Versión con promesa de searchContactByEmail
     */
    public function searchContactByEmailPromise($email)
    {
        return Promise\Create::promiseFor($this->searchContactByEmail($email));
    }

    /**
     * Versión con promesa de createContact
     */
    public function createContactPromise($user)
    {
        return Promise\Create::promiseFor($this->createContact($user));
    }

    /**
     * Versión con promesa de getUserIdByEmail
     */
    public function getUserIdByEmailPromise($email)
    {
        return Promise\Create::promiseFor($this->getUserIdByEmail($email));
    }

    /**
     * Versión con promesa de createProjectFromHubspotDeal
     */
    public function createProjectFromHubspotDealPromise($hubspotDeal, $customerId, $camposDeTeamleader)
    {
        return Promise\Create::promiseFor(
            $this->createProjectFromHubspotDeal($hubspotDeal, $customerId, $camposDeTeamleader)
        );
    }

    /**
     * Versión con promesa de searchContactByPassport
     */
    public function searchContactByPassportPromise($passportNumber)
    {
        return Promise\Create::promiseFor($this->searchContactByPassport($passportNumber));
    }

    /**
     * Versión con promesa de updateProject
     */
    public function updateProjectPromise($projectId, $updatedData)
    {
        return Promise\Create::promiseFor($this->updateProject($projectId, $updatedData));
    }

    public function findCustomFieldIdByLabel(string $label, string $context = 'project', int $pageSize = 100): ?string
    {
        try {
            $accessToken = $this->getAccessToken();
            $pageNumber = 1;

            do {
                $payload = [
                    'filter' => [
                        'context' => $context,
                    ],
                    'page' => [
                        'size' => $pageSize,
                        'number' => $pageNumber,
                    ],
                    'sort' => [
                        ['field' => 'label', 'direction' => 'asc']
                    ]
                ];

                $response = Http::withToken($accessToken)
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                    ])
                    ->post('https://api.focus.teamleader.eu/customFieldDefinitions.list', $payload);

                if ($response->successful()) {
                    $data = $response->json();
                    $customFields = $data['data'] ?? [];

                    dd($data);

                    // Buscar el campo con el label exacto
                    foreach ($customFields as $field) {
                        if (strtolower($field['label']) === strtolower($label)) {
                            return $field['id'];
                        }
                    }

                    dd($customFields);

                    // Verificar si hay más páginas
                    $hasMorePages = !empty($data['meta']['page']['next']) || ($data['meta']['page']['total'] > ($pageNumber * $pageSize));
                    $pageNumber++;
                } else {
                    $error = $response->json();
                    $errorMessage = $error['errors'][0]['title'] ?? 'Error desconocido';
                    throw new \Exception('Error al listar campos personalizados: ' . $errorMessage);
                }
            } while ($hasMorePages);

            // No se encontró el campo
            return null;
        } catch (\Exception $e) {
            throw new \Exception('Error al buscar el ID del campo personalizado: ' . $e->getMessage());
        }
    }

    /**
     * Listar todos los proyectos de Teamleader con todos sus detalles, incluyendo solo el campo personalizado "PRODUCTO".
     *
     * @return array Lista de proyectos con detalles y solo el campo personalizado "PRODUCTO"
     * @throws \Exception
     */
    public function listAllProjectsWithDetails(): array
    {
        try {
            // Buscar el ID del campo personalizado "PRODUCTO"
            $customFieldId = "fcd48891-20f6-049a-a05f-f78a6f951b4d";

            if (is_null($customFieldId)) {
                throw new \Exception('No se encontró el campo personalizado con el nombre "PRODUCTO" para proyectos.');
            }

            $accessToken = $this->getAccessToken();
            $allProjects = [];
            $pageNumber = 1;
            $pageSize = 100; // Tamaño máximo por página según la API

            // Obtener todos los proyectos con paginación
            do {
                $payload = [
                    'page' => [
                        'size' => $pageSize,
                        'number' => $pageNumber,
                    ],
                ];

                $response = Http::withToken($accessToken)
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                    ])
                    ->post('https://api.focus.teamleader.eu/projects.list', $payload);

                if ($response->successful()) {
                    $data = $response->json();
                    $projects = $data['data'] ?? [];
                    $allProjects = array_merge($allProjects, $projects);

                    dd($data['meta']['page']);

                    // Verificar si hay más páginas
                    $hasMorePages = false;
                    $pageNumber++;
                } else {
                    $error = $response->json();
                    $errorMessage = $error['errors'][0]['title'] ?? 'Error desconocido';
                    throw new \Exception('Error al listar proyectos: ' . $errorMessage);
                }
            } while ($hasMorePages);

            /*

            // Obtener detalles completos de cada proyecto en paralelo
            $detailPromises = [];
            foreach ($allProjects as $project) {
                $detailPromises['project_' . $project['id']] = $this->getProjectDetailsPromise($project['id']);
            }

            $details = $this->executeConcurrent($detailPromises);

            // Combinar proyectos con sus detalles, filtrando solo el campo personalizado "PRODUCTO"
            $detailedProjects = [];
            foreach ($allProjects as $project) {
                $projectId = $project['id'];
                $detailKey = 'project_' . $projectId;
                if (isset($details[$detailKey])) {
                    // Filtrar los custom_fields para incluir solo "PRODUCTO"
                    $filteredCustomFields = [];
                    if (!empty($details[$detailKey]['custom_fields'])) {
                        foreach ($details[$detailKey]['custom_fields'] as $field) {
                            if ($field['id'] === $customFieldId) {
                                $filteredCustomFields[] = $field;
                                break;
                            }
                        }
                    }
                    // Reemplazar los custom_fields originales con el filtrado
                    $details[$detailKey]['custom_fields'] = $filteredCustomFields;
                    $detailedProjects[] = array_merge($project, $details[$detailKey]);
                } else {
                    // Si no hay detalles, incluir el proyecto sin custom_fields
                    $project['custom_fields'] = [];
                    $detailedProjects[] = $project;
                }
            }

            return $detailedProjects;*/
        } catch (\Exception $e) {
            throw new \Exception('Error al obtener todos los proyectos con detalles: ' . $e->getMessage());
        }
    }

    /**
     * Versión con promesa de listAllProjectsWithDetails
     *
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public function listAllProjectsWithDetailsPromise()
    {
        return Promise\Create::promiseFor($this->listAllProjectsWithDetails());
    }

    public function listProjectsPage(int $pageNumber, int $pageSize = 100): array
    {
        try {
            $accessToken = $this->getAccessToken();
            $customFieldId = "fcd48891-20f6-049a-a05f-f78a6f951b4d"; // ID del campo PRODUCTO

            $payload = [
                'page' => [
                    'size' => $pageSize,
                    'number' => $pageNumber,
                ],
            ];

            $response = Http::withToken($accessToken)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post('https://api.focus.teamleader.eu/projects.list', $payload);

            if (!$response->successful()) {
                $error = $response->json();
                $errorMessage = $error['errors'][0]['title'] ?? 'Error desconocido';
                throw new \Exception('Error al listar proyectos: ' . $errorMessage);
            }

            $data = $response->json();
            $projects = $data['data'] ?? [];

            // --- Obtener detalles de cada proyecto ---
            $enrichedProjects = [];
            foreach ($projects as $project) {
                $details = $this->getProjectDetails($project['id']);
                $filteredCustomFields = [];

                if (!empty($details['custom_fields'])) {
                    foreach ($details['custom_fields'] as $field) {
                        if ($field['definition']['id'] === $customFieldId) {
                            $filteredCustomFields[] = [
                                'id' => $field['definition']['id'],
                                'label' => $this->getCustomFieldLabel($field['definition']['id'], 'project'),
                                'value' => $field['value'],
                            ];
                        }
                    }
                }

                $project['custom_fields'] = $filteredCustomFields;

                $enrichedProjects[] = $project;
            }

            // devolver misma estructura que antes, pero con proyectos enriquecidos
            $data['data'] = $enrichedProjects;
            return $data;

        } catch (\Exception $e) {
            throw new \Exception("Error en {$e->getFile()} línea {$e->getLine()}: " . $e->getMessage());
        }
    }


}
