<?php

namespace App\Services;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Negocio;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class UserSyncService
{
    private $hubspotService;
    private $teamleaderService;

    public function __construct($hubspotService, $teamleaderService)
    {
        $this->hubspotService = $hubspotService;
        $this->teamleaderService = $teamleaderService;
    }

    /**
     * Sincroniza datos del usuario con HubSpot
     * Usa cache de 5 minutos
     */
    public function syncWithHubspot(User $user): array
    {
        $cacheKey = "hubspot_data_{$user->hs_id}";

        return Cache::remember($cacheKey, 300, function () use ($user) {
            if (is_null($user->hs_id)) {
                $this->ensureHubspotContact($user);
            }

            return [
                'contact' => $this->hubspotService->getContactById($user->hs_id),
                'files' => $this->hubspotService->getContactFileFields($user->hs_id),
                'deals' => $this->hubspotService->getDealsByContactId($user->hs_id),
            ];
        });
    }

    /**
     * Sincroniza datos del usuario con Teamleader
     * Usa cache de 5 minutos
     */
    public function syncWithTeamleader(User $user): array
    {
        $cacheKey = "teamleader_data_{$user->tl_id}";

        return Cache::remember($cacheKey, 300, function () use ($user) {
            if (is_null($user->tl_id)) {
                $this->ensureTeamleaderContact($user);
            }

            return [
                'contact' => $this->teamleaderService->getContactById($user->tl_id),
                'deals' => $this->teamleaderService->getProjectsWithDetailsByCustomerId($user->tl_id),
            ];
        });
    }

    /**
     * Asegura que el usuario tenga un contacto en HubSpot
     */
    private function ensureHubspotContact(User $user): void
    {
        try {
            // Buscar por email primero
            $existingContact = $this->hubspotService->searchContactByEmail($user->email);

            if ($existingContact) {
                $user->hs_id = $existingContact['id'];
                $user->save();

                Log::info("HubSpot: Contacto existente vinculado por email", [
                    'user_id' => $user->id,
                    'hs_id' => $existingContact['id']
                ]);
                return;
            }

            // Si no existe por email, buscar por pasaporte
            if (!empty($user->passport)) {
                $existingContact = $this->hubspotService->searchContactByPassport($user->passport);

                if ($existingContact) {
                    $user->hs_id = $existingContact['id'];
                    $user->save();

                    Log::info("HubSpot: Contacto existente vinculado por pasaporte", [
                        'user_id' => $user->id,
                        'hs_id' => $existingContact['id']
                    ]);
                    return;
                }
            }

            // Si no existe, crear nuevo contacto
            $contactData = $this->prepareHubspotContactData($user);
            $newContactId = $this->hubspotService->createContact($contactData);

            if ($newContactId) {
                $user->hs_id = $newContactId;
                $user->save();

                Log::info("HubSpot: Nuevo contacto creado", [
                    'user_id' => $user->id,
                    'hs_id' => $newContactId
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Error al sincronizar contacto con HubSpot", [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Asegura que el usuario tenga un contacto en Teamleader
     */
    private function ensureTeamleaderContact(User $user): void
    {
        if (is_null($user->tl_id)) {
            try {
                $TLcontact = $this->teamleaderService->searchContactByEmail($user->email);

                if (!is_null($TLcontact)) {
                    $user->tl_id = $TLcontact['id'];
                } else {
                    $newContact = $this->teamleaderService->createContact($user);
                    $user->tl_id = $newContact['id'];
                }

                $user->save();

                Log::info("Teamleader: Contacto sincronizado", [
                    'user_id' => $user->id,
                    'tl_id' => $user->tl_id
                ]);
            } catch (\Exception $e) {
                Log::error("Error al sincronizar con Teamleader", [
                    'user_id' => $user->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Prepara los datos del usuario para crear contacto en HubSpot
     */
    private function prepareHubspotContactData(User $user): array
    {
        $properties = [
            'email' => $user->email,
            'firstname' => $user->nombres ?? '',
            'lastname' => $user->apellidos ?? '',
            'phone' => $user->phone ?? '',
            'numero_de_pasaporte' => $user->passport ?? '',
        ];

        // Fecha de nacimiento
        if (!empty($user->date_of_birth) && $user->date_of_birth != '0000-00-00') {
            try {
                $date = new \DateTime($user->date_of_birth, new \DateTimeZone('UTC'));
                $properties['fecha_nac'] = $date->getTimestamp() * 1000;
            } catch (\Exception $e) {
                Log::warning("Fecha de nacimiento inválida", [
                    'user_id' => $user->id,
                    'date_of_birth' => $user->date_of_birth
                ]);
            }
        }

        // Género
        if (!empty($user->genero)) {
            $genderMapping = [
                'MASCULINO' => 'MASCULINO / MALE',
                'FEMENINO' => 'FEMENINO / FEMALE',
                'OTROS' => 'OTROS / OTHERS',
            ];
            $properties['genero'] = $genderMapping[trim($user->genero)] ?? $user->genero;
        }

        // Otros campos opcionales
        $optionalFields = [
            'servicio_solicitado' => 'servicio',
            'country' => 'country',
            'city' => 'city',
            'address' => 'address',
            'n000__referido_por__clonado_' => 'referido_por',
        ];

        foreach ($optionalFields as $hsField => $dbField) {
            if (!empty($user->{$dbField})) {
                $properties[$hsField] = $user->{$dbField};
            }
        }

        // Filtrar vacíos
        return array_filter($properties, fn($value) => $value !== null && $value !== '');
    }

    /**
     * Calcula actualizaciones necesarias entre HubSpot y DB
     */
    public function calculateFieldUpdates(User $user, array $hsContact): array
    {
        $hubspotFields = $this->getHubspotFieldMapping();
        $updatesToDB = [];
        $updatesToHubSpot = [];

        $hsLastModified = new \DateTime($hsContact['properties']['lastmodifieddate']);
        $dbLastModified = new \DateTime($user->updated_at);
        $utcTimezone = new \DateTimeZone('UTC');

        $hsLastModified->setTimezone($utcTimezone);
        $dbLastModified->setTimezone($utcTimezone);

        // Expandir campos dinámicamente
        foreach ($hsContact['properties'] as $hsField => $value) {
            if (!array_key_exists($hsField, $hubspotFields)
                && !in_array($hsField, ['createdate', 'hs_object_id'])) {
                $hubspotFields[$hsField] = $hsField;
            }
        }

        foreach ($hubspotFields as $hsField => $dbField) {
            if ($hsField === 'lastmodifieddate') continue;

            $hubspotValue = $hsContact['properties'][$hsField] ?? null;
            $dbValue = $user->{$dbField} ?? null;

            if ($hubspotValue === $dbValue) continue;

            // HubSpot más reciente -> actualizar DB
            if ($hubspotValue && (!$dbValue || $hsLastModified > $dbLastModified)) {
                $processedValue = $this->processFieldValue($hsField, $hubspotValue, $dbValue);
                if ($processedValue !== null) {
                    $updatesToDB[$dbField] = $processedValue;
                }
            }
            // DB más reciente -> actualizar HubSpot
            elseif ($dbValue && (!$hubspotValue || $dbLastModified > $hsLastModified)) {
                $processedValue = $this->processFieldForHubspot($hsField, $dbValue, $hubspotValue);
                if ($processedValue !== null) {
                    $updatesToHubSpot[$hsField] = $processedValue;
                }
            }
        }

        // Filtrar campos excluidos
        $updatesToHubSpot = array_filter(
            $updatesToHubSpot,
            fn($key) => !in_array($key, ['lastmodifieddate', 'referido_por']),
            ARRAY_FILTER_USE_KEY
        );

        return compact('updatesToDB', 'updatesToHubSpot');
    }

    /**
     * Procesa valores de campos para guardar en DB
     */
    private function processFieldValue(string $field, $hsValue, $dbValue)
    {
        // Protección para fecha de nacimiento
        if (in_array($field, ['fecha_nac', 'date_of_birth'])) {
            if (!empty($dbValue) && $dbValue != '0000-00-00') {
                return null;
            }

            if (is_numeric($hsValue)) {
                $date = (new \DateTime())->setTimestamp($hsValue / 1000);
                return $date->format('Y-m-d');
            }
        }

        return $hsValue;
    }

    /**
     * Procesa valores de campos para enviar a HubSpot
     */
    private function processFieldForHubspot(string $field, $dbValue, $hsValue)
    {
        switch ($field) {
            case 'fecha_nac':
            case 'date_of_birth':
                if (empty($dbValue) || $dbValue == "0000-00-00") {
                    return null;
                }

                try {
                    $onlyDate = (new \DateTime($dbValue))->format('Y-m-d');
                    $dbDate = new \DateTime($onlyDate, new \DateTimeZone('UTC'));
                    $dbTimestampMs = $dbDate->getTimestamp() * 1000;

                    $hsTimestampMs = null;
                    if ($hsValue !== null && is_numeric($hsValue)) {
                        $hsDate = (new \DateTime())->setTimestamp($hsValue / 1000);
                        $hsDate->setTimezone(new \DateTimeZone('UTC'));
                        $hsTimestampMs = $hsDate->getTimestamp() * 1000;
                    }

                    return $hsTimestampMs !== $dbTimestampMs ? $dbTimestampMs : null;
                } catch (\Exception $e) {
                    return null;
                }

            case 'genero':
                $mapping = [
                    'MASCULINO' => 'MASCULINO / MALE',
                    'FEMENINO' => 'FEMENINO / FEMALE',
                    'OTROS' => 'OTROS / OTHERS',
                ];
                $cleanValue = trim($dbValue);
                $mappedValue = $mapping[$cleanValue] ?? null;

                return ($mappedValue && $hsValue !== $mappedValue) ? $mappedValue : null;

            case 'cantidad_alzada':
                return (strval($hsValue) !== strval($dbValue)) ? $dbValue : null;

            default:
                return ($hsValue !== $dbValue) ? $dbValue : null;
        }
    }

    /**
     * Mapeo de campos entre HubSpot y DB
     */
    private function getHubspotFieldMapping(): array
    {
        return [
            'fecha_nac' => 'date_of_birth',
            'firstname' => 'nombres',
            'lastmodifieddate' => 'updated_at',
            'lastname' => 'apellidos',
            'n000__referido_por__clonado_' => 'referido_por',
            'numero_de_pasaporte' => 'passport',
            'servicio_solicitado' => 'servicio',
        ];
    }
}
