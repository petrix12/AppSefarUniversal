<?php

namespace App\Services;

use HubSpot\Factory;
use HubSpot\Client\Crm\Contacts\ApiException as ContactException;
use HubSpot\Client\Crm\Deals\ApiException as DealException;
use HubSpot\Client\Crm\Associations\Model\BatchInputPublicObjectId;
use HubSpot\Client\Crm\Associations\ApiException as AssociationsApiException;
use HubSpot\Client\Crm\Properties\ApiException as PropertiesApiException;
use HubSpot\Client\Crm\Deals\Model\BatchReadInputSimplePublicObjectId;
use App\Models\AssocTlHs;
use GuzzleHttp\Exception\RequestException;
use HubSpot\Client\Files\ApiException as FilesApiException;
use GuzzleHttp\Client as GuzzleClient;

use HubSpot\Client\Files\Model\FileUpdateInput;

class HubspotService
{
    protected $hubspot;

    public function __construct()
    {
        $this->hubspot = Factory::createWithAccessToken(env('HUBSPOT_KEY'));
    }

    /**
     * Buscar un contacto por correo electrónico.
     */
    public function searchContactByEmail($email)
    {
        try {
            $filter = new \HubSpot\Client\Crm\Contacts\Model\Filter();
            $filter
                ->setOperator('EQ')
                ->setPropertyName('email')
                ->setValue($email);

            $filterGroup = new \HubSpot\Client\Crm\Contacts\Model\FilterGroup();
            $filterGroup->setFilters([$filter]);

            $searchRequest = new \HubSpot\Client\Crm\Contacts\Model\PublicObjectSearchRequest();
            $searchRequest->setFilterGroups([$filterGroup]);
            $searchRequest->setProperties(['email']); // Puedes agregar más propiedades si lo deseas
            $searchRequest->setLimit(1);

            $contactsPage = $this->hubspot->crm()->contacts()->searchApi()->doSearch($searchRequest);

            if (count($contactsPage->getResults()) > 0) {
                $contact = $contactsPage->getResults()[0];
                return [
                    'id' => $contact->getId(),
                    'properties' => $contact->getProperties(),
                ];
            } else {
                // No se encontró el contacto
                return null;
            }
        } catch (ContactException $e) {
            throw new \Exception('Error al buscar el contacto en HubSpot: ' . $e->getMessage());
        }
    }

    /**
     * Obtener un contacto por ID.
     */
    public function getContactById($id)
    {
        try {
            // Campos adicionales requeridos de HubSpot
            $requiredHubspotFields = [
                'fecha_nac',
                'firstname',
                'lastmodifieddate',
                'lastname',
                'n000__referido_por__clonado_',
                'numero_de_pasaporte',
                'servicio_solicitado',
            ];

            // Obtener todas las propiedades disponibles para contactos desde HubSpot
            $allPropertiesResponse = $this->hubspot->crm()->properties()->coreApi()->getAll('contacts');
            $allProperties = $allPropertiesResponse->getResults();

            // Extraer los nombres de las propiedades que coinciden con los campos de la base de datos
            $databaseFields = \Schema::getColumnListing('users'); // Cambia 'users' por el nombre de tu tabla si es diferente
            $matchingFields = array_filter($allProperties, function ($property) use ($databaseFields) {
                return in_array($property->getName(), $databaseFields);
            });

            // Combinar los campos coincidentes con los requeridos
            $propertyNames = array_merge(
                array_map(function ($property) {
                    return $property->getName();
                }, $matchingFields),
                $requiredHubspotFields
            );

            // Eliminar duplicados en las propiedades
            $propertyNames = array_unique($propertyNames);

            // Preparar la solicitud para obtener el contacto con las propiedades seleccionadas
            $batchReadInputSimplePublicObjectId = new \HubSpot\Client\Crm\Contacts\Model\BatchReadInputSimplePublicObjectId([
                'properties' => $propertyNames,
                'inputs' => [
                    ['id' => $id],
                ],
            ]);

            $batchResponse = $this->hubspot->crm()->contacts()->batchApi()->read($batchReadInputSimplePublicObjectId);

            // Procesar el resultado
            if (count($batchResponse->getResults()) > 0) {
                $contact = $batchResponse->getResults()[0];
                return [
                    'id' => $contact->getId(),
                    'properties' => $contact->getProperties(),
                ];
            } else {
                throw new \Exception('Contacto no encontrado en HubSpot.');
            }
        } catch (\Exception $e) {
            throw new \Exception('Error al obtener el contacto en HubSpot: ' . $e->getMessage());
        }
    }

    public function updateContact($hsId, $properties)
    {
        try {
            $this->hubspot->crm()->contacts()->basicApi()->update($hsId, [
                'properties' => $properties
            ]);
        } catch (ContactException $e) {
            // Aquí puedes ver el código de estado (por ejemplo, 400) y la respuesta completa
            $statusCode   = $e->getCode();
            $responseBody = $e->getResponseBody();

            // Muestra (o loguea) la respuesta completa para ver el error sin truncar
            dd([
                'HubSpot Update Error',
                'Status Code' => $statusCode,
                'Response'    => json_decode($responseBody),
                'Propiedades Enviadas' => $properties
            ]);
        }
    }

    public function getDealProperties(): array
    {
        try {
            $allPropertiesResponse = $this->hubspot
                                        ->crm()
                                        ->properties()
                                        ->coreApi()
                                        ->getAll('deals');

            $allProperties = $allPropertiesResponse->getResults();

            // Extrae solo los nombres de las propiedades
            return array_map(fn($prop) => $prop->getName(), $allProperties);

        } catch (PropertiesApiException $e) {
            throw new \Exception('Error al obtener propiedades de negocios (deals): '.$e->getMessage());
        } catch (\Exception $e) {
            throw new \Exception('Error general al obtener propiedades de negocios: '.$e->getMessage());
        }
    }

    public function getDealsByContactId(string $contactId): array
    {
        try {
            // 1. Obtener todas las propiedades de "deals" para luego leerlas
            $properties = $this->getDealProperties();

            // 2. Obtener las asociaciones (contact -> deals)
            $associations = $this->hubspot->crm()->associations()->batchApi()->read(
                'contacts', // objeto origen
                'deals',    // objeto destino
                new BatchInputPublicObjectId([
                    'inputs' => [
                        ['id' => $contactId],
                    ],
                ])
            );

            // 3. Extraer todos los IDs de 'deals' asociados a este contacto
            $dealIds = [];
            foreach ($associations->getResults() as $association) {
                // 'getTo()' retorna un array de objetos PublicObjectId
                $toArray = $association->getTo();
                foreach ($toArray as $toItem) {
                    $dealIds[] = $toItem->getId();
                }
            }

            if (empty($dealIds)) {
                // No hay negocios asociados
                return [];
            }

            // 4. Crear la request para leer en batch los negocios obtenidos
            $batchRequest = new BatchReadInputSimplePublicObjectId([
                'properties' => $properties, // las propiedades de deals que queremos
                'inputs' => array_map(
                    fn($id) => ['id' => $id],
                    $dealIds
                ),
            ]);

            // 5. Hacemos la lectura batch de Deals
            $dealsResponse = $this->hubspot->crm()->deals()->batchApi()->read($batchRequest);

            // 6. Retornamos un array con la información de cada deal
            return array_map(function ($deal) {
                return [
                    'id' => $deal->getId(),
                    'properties' => $deal->getProperties(),
                ];
            }, $dealsResponse->getResults());

        } catch (\Exception $e) {
            throw new \Exception('Error al obtener los negocios asociados al contacto: ' . $e->getMessage());
        }
    }

    public function getEngagementsByContactId(string $contactId): array
    {
        try {
            // 1. Obtener los IDs de engagement asociados al contacto (API v3 de Associations)
            $associations = $this->hubspot->crm()->associations()->batchApi()->read(
                'contacts',
                'engagements',
                new \HubSpot\Client\Crm\Associations\Model\BatchInputPublicObjectId([
                    'inputs' => [
                        ['id' => $contactId],
                    ],
                ])
            );

            // Extraemos los IDs de los engagements
            $engagementIds = [];
            foreach ($associations->getResults() as $association) {
                foreach ($association->getTo() as $toItem) {
                    $engagementIds[] = $toItem->getId();
                }
            }

            if (empty($engagementIds)) {
                // No hay engagements => No habrá adjuntos
                return [];
            }

            // 2. Cliente Legacy (v1) con Guzzle para leer los engagements
            $legacyClient = new GuzzleClient([
                'base_uri' => 'https://api.hubapi.com',
            ]);
            $token = env('HUBSPOT_KEY');

            // 3. Cliente v3 de archivos (File Manager)
            $filesClient = Factory::createWithAccessToken($token);

            // Array para almacenar todas las URLs (v3)
            $fileUrls = [];

            // 4. Recorrer los engagements y obtener los adjuntos
            foreach ($engagementIds as $engagementId) {
                // Llamada a /engagements/v1/engagements/{engagementId}
                $resp = $legacyClient->request('GET', "/engagements/v1/engagements/{$engagementId}", [
                    'headers' => [
                        'Authorization' => "Bearer {$token}",
                        'Content-Type'  => 'application/json',
                    ],
                ]);
                $engagement = json_decode($resp->getBody(), true);

                if (!empty($engagement['attachments'])) {
                    foreach ($engagement['attachments'] as $attach) {
                        // 4.a) Verificamos si hay un fileId en el adjunto
                        if (!empty($attach['id'])) {

                            try {
                                // 5. Obtener detalles del archivo por ID (v3)
                                $apiResponse = $filesClient->files()->filesApi()->getById($attach['id']);
                                // 5.a) Revisamos si existe url (p.ej. "https://...pdf")
                                if (
                                    // Verifica que sea público
                                    in_array($apiResponse->getAccess(), ['PUBLIC_INDEXABLE', 'PUBLIC_NOT_INDEXABLE'], true)
                                    // ... y que tenga una URL
                                    && !empty($apiResponse->getUrl())
                                ) {
                                    $fileUrls[] = $apiResponse->getUrl();
                                }
                            } catch (FilesApiException $ex) {
                                // Si el archivo está hidden o no tienes permisos, atrapará la excepción
                                // Puedes ignorar o loguear el error.
                            }
                        }
                    }
                }
            }

            // 6. Devolvemos el array con URLs
            return $fileUrls;

        } catch (RequestException $e) {
            throw new \Exception(
                "Error al obtener engagements/attachments: " . $e->getMessage()
            );
        } catch (\Exception $e) {
            throw new \Exception(
                "Error general al obtener enlaces de archivos: " . $e->getMessage()
            );
        }
    }

    private function extractFileIdFromFormIntegrationsUrl(string $url): ?string
    {
        // Ejemplo de URL:
        // https://api-na1.hubspot.com/form-integrations/v1/uploaded-files/signed-url-redirect/184376969047?portalId=20053496&sign=...
        //
        // Usamos una expresión regular para capturar el número
        $pattern = '#signed-url-redirect/(\d+)#';

        if (preg_match($pattern, $url, $matches)) {
            // $matches[1] contendrá la parte capturada (\d+)
            return $matches[1];
        }

        return null;
    }

    public function getFileUrlFromFormIntegrations(string $rawUrl): ?string
    {
        // 1. Extraer el ID del archivo desde la URL
        $fileId = $this->extractFileIdFromFormIntegrationsUrl($rawUrl);
        if (empty($fileId)) {
            // No se obtuvo un ID => no podemos procesar
            return null;
        }

        try {
            // 2. Obtener detalles actuales del archivo
            $fileDetails = $this->hubspot
                ->files()
                ->filesApi()
                ->getById($fileId);

            // 3. Si el archivo es PRIVATE, lo hacemos público
            if ($fileDetails->getAccess() === 'PRIVATE') {
                // Crea el FileUpdateInput según la doc de HubSpot
                // puedes añadir otros campos si lo necesitas
                $fileUpdateInput = new FileUpdateInput([
                    'access'             => 'PUBLIC_NOT_INDEXABLE',
                ]);

                // Llamamos a updateProperties para cambiarlo a público
                $updatedFile = $this->hubspot
                    ->files()
                    ->filesApi()
                    ->updateProperties($fileId, $fileUpdateInput);

                // $updatedFile ahora debería tener access = PUBLIC_...
                $fileDetails = $updatedFile;
            }

            // 4. Verificamos si el archivo es público y tiene URL
            if (
                in_array($fileDetails->getAccess(), ['PUBLIC_INDEXABLE', 'PUBLIC_NOT_INDEXABLE'], true)
                && !empty($fileDetails->getUrl())
            ) {
                // Devolvemos la URL pública del archivo
                return $fileDetails->getUrl();
            }

            // Si sigue siendo privado o no hay URL, devolvemos null
            return null;

        } catch (FilesApiException $ex) {
            // El archivo podría ser privado y no tener permisos,
            // o no existir. Manejar según tu criterio.
            return null;
        } catch (RequestException $ex) {
            // Error de red / Guzzle
            return null;
        } catch (\Exception $ex) {
            // Cualquier otro error genérico
            return null;
        }
    }

    /**
     * Retorna un array con URLs de los archivos que se encuentran
     * en propiedades del contacto (no en Engagements).
     *
     * @param  string $contactId       El ID del contacto en HubSpot
     * @param  array  $fileProperties  Lista de propiedades que podrían contener archivos
     * @return array                   Array de URLs de archivos disponibles
     */
    public function getContactFileFields(string $contactId): array
    {
        try {
            // 1. Preparar el token y el cliente de HubSpot (o bien reusar $this->hubspot)
            $token    = env('HUBSPOT_KEY');
            $hubspot  = Factory::createWithAccessToken($token);

            // 2. Obtener el contacto con las propiedades que nos interesan
            //    - Si tienes propiedades: ['mi_archivo_cv', 'foto_del_contacto', ...]
            //      pásalas en el segundo parámetro de getById con implode()
            //    - Ejemplo: "mi_archivo_cv,foto_del_contacto"

            $fileProperties = ["pasaporte__documento_", "partida_de_nacimiento_simple__"];
            $propertiesToRequest = implode(',', $fileProperties);

            //    - OJO: Usa basicApi()->getById(...) de la CRM v3
            //      https://developers.hubspot.com/docs/api/crm/contacts
            $contactResponse = $hubspot
                ->crm()
                ->contacts()
                ->basicApi()
                ->getById(
                    $contactId,
                    $propertiesToRequest
                );

            // 3. Extraer las propiedades y sus valores
            $properties = $contactResponse->getProperties(); // array asociativo: ['mi_archivo_cv' => '...', ...]

            // 4. Array donde guardaremos las URLs finales
            $fileUrls = [];

            // 5. Recorremos cada propiedad "de archivo" para ver si hay contenido
            foreach ($fileProperties as $propName) {
                if (!empty($properties[$propName])) {
                    $propValue = $properties[$propName];

                    // CASO A: El valor es un ID numérico (ej. "123456")
                    if (is_numeric($propValue)) {
                        $this->tryAddFileUrl($fileUrls, $propValue, $hubspot);

                    // CASO B: El valor es una URL directa
                    } elseif (filter_var($propValue, FILTER_VALIDATE_URL)) {
                        $fileUrls[] = $propValue;

                    // CASO C: El valor podría ser un JSON con varios IDs/URLs (por ejemplo, multi-file property)
                    } elseif ($this->isJson($propValue)) {
                        $decoded = json_decode($propValue, true);
                        if (is_array($decoded)) {
                            foreach ($decoded as $item) {
                                // Si es un ID, llamamos a la Files API
                                if (is_numeric($item)) {
                                    $this->tryAddFileUrl($fileUrls, $item, $hubspot);

                                // Si es una URL, la agregamos tal cual
                                } elseif (filter_var($item, FILTER_VALIDATE_URL)) {
                                    $fileUrls[] = $item;
                                }
                            }
                        }
                    }
                }
            }

            return $fileUrls;

        } catch (ContactException $e) {
            throw new \Exception("Error al obtener contacto (ContactException): " . $e->getMessage());
        } catch (FilesApiException $e) {
            throw new \Exception("Error al obtener archivos (FilesApiException): " . $e->getMessage());
        } catch (\Exception $e) {
            throw new \Exception("Error general al obtener archivos de las propiedades: " . $e->getMessage());
        }
    }

    /**
     * Intenta obtener la URL pública de un archivo mediante la Files API v3,
     * y si está disponible (pública), la agrega al array $fileUrls.
     *
     * @param  array     &$fileUrls  Referencia al array de URLs
     * @param  string    $fileId     ID del archivo en HubSpot
     * @param  \HubSpot\HubSpot $hubspot Instancia del HubSpot client
     */
    private function tryAddFileUrl(array &$fileUrls, string $fileId, $hubspot)
    {
        try {
            $fileDetails = $hubspot->crm()->files()->filesApi()->getById($fileId);

            // Filtra sólo los que tengan 'access' público
            if (
                in_array($fileDetails->getAccess(), ['PUBLIC_INDEXABLE', 'PUBLIC_NOT_INDEXABLE'], true)
                && !empty($fileDetails->getUrl())
            ) {
                $fileUrls[] = $fileDetails->getUrl();
            }
        } catch (FilesApiException $ex) {
            // Si el archivo es privado o no existe, ignorar o loguear
            // Log::warning("No se pudo obtener el archivo con ID {$fileId}: " . $ex->getMessage());
        }
    }

    /**
     * Verifica rápidamente si un string es JSON válido.
     */
    private function isJson($string): bool
    {
        if (!is_string($string)) {
            return false;
        }
        json_decode($string);
        return (json_last_error() === JSON_ERROR_NONE);
    }

}
