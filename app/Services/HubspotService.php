<?php

namespace App\Services;

use HubSpot\Factory;
use HubSpot\Client\Crm\Contacts\Model\SimplePublicObjectInput;
use HubSpot\Client\Crm\Contacts\Model\BatchInputSimplePublicObjectBatchInput as ContactBatchInput;
use HubSpot\Client\Crm\Contacts\Model\SimplePublicObjectBatchInput as ContactBatchObjectInput;
use HubSpot\Client\Crm\Contacts\ApiException as ContactException;
use HubSpot\Client\Crm\Deals\ApiException as DealException;
use HubSpot\Client\Crm\Deals\Model\BatchInputSimplePublicObjectBatchInput as DealBatchInput;
use HubSpot\Client\Crm\Deals\Model\SimplePublicObjectBatchInput as DealBatchObjectInput;
use HubSpot\Client\Crm\Associations\Model\BatchInputPublicObjectId;
use HubSpot\Client\Crm\Associations\ApiException as AssociationsApiException;
use HubSpot\Client\Crm\Properties\ApiException as PropertiesApiException;
use HubSpot\Client\Crm\Deals\Model\BatchReadInputSimplePublicObjectId;
use App\Models\AssocTlHs;
use App\Models\Compras;
use App\Models\Factura;
use App\Models\Negocio;
use App\Models\Servicio;
use App\Models\User;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\ClientInterface;
use HubSpot\Client\Files\ApiException as FilesApiException;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Promise;
use GuzzleHttp\Psr7\Response;
use HubSpot\Client\Crm\Contacts\Model\Filter;
use HubSpot\Client\Crm\Contacts\Model\FilterGroup;
use HubSpot\Client\Crm\Contacts\Model\PublicObjectSearchRequest;
use HubSpot\Client\Settings\Users\ApiException as UsersApiException;
use HubSpot\Client\Settings\Users\Model\UserProvisionRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

use HubSpot\Client\Files\Model\FileUpdateInput;

class HubspotService
{
    protected $hubspot;

    private const FORMULARIO_001_COMMON_FIELDS = [
        'email' => ['label' => 'Correo'],
        'city' => ['label' => 'Ciudad de residencia'],
        'address' => ['label' => 'Dirección'],
        'genero' => ['label' => 'Género'],
        'edo_civil' => ['label' => 'Estado civil'],
        'fecha_nac' => ['label' => 'Fecha de nacimiento'],
        'ciudad_de_nacimiento' => ['label' => 'Ciudad de nacimiento'],
        'nombres_y_apellidos_del_padre' => ['label' => 'Nombres y apellidos del padre'],
        'nombres_y_apellidos_de_madre' => ['label' => 'Nombres y apellidos de la madre'],
        'fecha_de_caducidad_del_pasaporte' => ['label' => 'Fecha de caducidad del pasaporte'],
        'pais_de_expedicion_del_pasaporte' => ['label' => 'País de expedición del pasaporte'],
        'tiene_hijos' => ['label' => 'Tiene hijos'],
        'cuantos_hijos_tiene_' => ['label' => 'Cantidad de hijos'],
        'nacionalidad_solicitada' => ['label' => 'Nacionalidad solicitada'],
        'requiere_tutor_o_representante_legal_' => ['label' => 'Requiere tutor o representante legal'],
        'pasaporte__documento_' => ['label' => 'Pasaporte simple', 'type' => 'file'],
        'partida_de_nacimiento_simple__' => ['label' => 'Partida de nacimiento simple', 'type' => 'file'],
        'documentos_adicionales' => ['label' => 'Documentos adicionales', 'type' => 'file'],
    ];

    /** Formulario 001 histórico, mostrado cuando no hay un 001 específico. */
    private const FORMULARIO_001_DEFAULT_FORM = [
        'service' => 'Formulario 001 predeterminado',
        'title' => 'Formulario 001',
        'form_id' => 'ae73e323-14a8-40f4-a20c-4a33a30aabde',
        'fields' => [
            'tengo_certeza_de_mi_antepasado_espanol_' => ['label' => 'Certeza sobre antepasado español'],
            'vinculo_antepasados' => ['label' => 'Vínculo con antepasado'],
        ],
    ];

    /** Formularios 001 y propiedades de contacto para cada servicio. */
    private const FORMULARIO_001_FORMS = [
        'nacionalidad portuguesa para familiares' => [
            'service' => 'Nacionalidad Portuguesa para Familiares',
            'title' => 'Formulario 001 · Nacionalidad Portuguesa para Familiares',
            'form_id' => '5d4f503d-401c-4482-99d4-ba48eeb77f54',
            'fields' => [
                'n1_que_parentesco_tiene_con_la_persona_de_nacionalidad_portuguesa' => ['label' => '1. ¿Qué parentesco tiene con la persona de nacionalidad portuguesa?'],
                'n2_como_obtuvo_la_nacionalidad_portuguesa_su_familiar' => ['label' => '2. ¿Cómo obtuvo la nacionalidad portuguesa su familiar?'],
                'n3_su_familiar_conserva_actualmente_la_nacionalidad_portuguesa' => ['label' => '3. ¿Su familiar conserva actualmente la nacionalidad portuguesa?'],
                'n4_su_familiar_reside_actualmente_en_portugal' => ['label' => '4. ¿Su familiar reside actualmente en Portugal?'],
                'n5_su_familiar_perdio_renuncio_o_recupero_en_algun_momento_la_nacionalidad_portuguesa' => ['label' => '5. ¿Su familiar perdió, renunció o recuperó en algún momento la nacionalidad portuguesa?'],
                'n3_su_padre_o_madre_ya_tenia_la_nacionalidad_portuguesa_cuando_usted_nacio' => ['label' => '¿Su padre o madre ya tenía la nacionalidad portuguesa cuando usted nació?'],
                'su_padre_o_madre_obtuvo_la_nacionalidad_portuguesa_despues_de_su_nacimiento' => ['label' => '¿Su padre o madre obtuvo la nacionalidad portuguesa después de su nacimiento?'],
                'n4_su_padremadre_hijoa_del_ciudadano_portugues_conserva_la_nacionalidad_portuguesa' => ['label' => '¿Su padre, madre, hijo o hija del ciudadano portugués conserva la nacionalidad portuguesa?'],
                'n5_cuenta_con_documentos_que_permitan_demostrar_el_vinculo_familiar_con_el_ciudadano_portugues' => ['label' => '¿Cuenta con documentos que permitan demostrar el vínculo familiar con el ciudadano portugués?'],
                'cuenta_con_las_partidas_de_nacimiento_que_acreditan_la_linea_familiar_hasta_el_ciudadano_portugues' => ['label' => '¿Cuenta con las partidas de nacimiento que acreditan la línea familiar hasta el ciudadano portugués?'],
                'tiene_la_partida_de_nacimiento_que_prueba_que_su_padremadre_es_hijoa_de_un_ciudadano_portugues' => ['label' => '¿Tiene la partida de nacimiento que prueba que su padre o madre es hijo o hija de un ciudadano portugués?'],
            ],
        ],
        'nacionalidad portuguesa por conyuge' => [
            'service' => 'Nacionalidad Portuguesa por Cónyuge',
            'title' => 'Formulario 001 · Nacionalidad Portuguesa por Cónyuge',
            'form_id' => 'db8b5601-39bb-468c-bfb4-b757a342ad4f',
            'fields' => [
                'n1_que_parentesco_tiene_con_la_persona_de_nacionalidad_portuguesa' => ['label' => '1. ¿Qué parentesco tiene con la persona de nacionalidad portuguesa?'],
                'que_parentesco_tiene_con_la_persona_de_nacionalidad_portuguesa' => ['label' => '¿Qué parentesco tiene con la persona de nacionalidad portuguesa?'],
                'n2_como_obtuvo_la_nacionalidad_portuguesa_su_familiar' => ['label' => '2. ¿Cómo obtuvo la nacionalidad portuguesa su familiar?'],
                'n3_su_familiar_conserva_actualmente_la_nacionalidad_portuguesa' => ['label' => '¿Su familiar conserva actualmente la nacionalidad portuguesa?'],
                'n4_su_familiar_reside_actualmente_en_portugal' => ['label' => '¿Su familiar reside actualmente en Portugal?'],
                'n5_su_familiar_perdio_renuncio_o_recupero_en_algun_momento_la_nacionalidad_portuguesa' => ['label' => '¿Su familiar perdió, renunció o recuperó en algún momento la nacionalidad portuguesa?'],
                'en_caso_afirmativo_que_ocurrio_con_la_nacionalidad_portuguesa_de_su_familiar' => ['label' => '¿Qué ocurrió con la nacionalidad portuguesa de su familiar?'],
                'n3_su_padre_o_madre_ya_tenia_la_nacionalidad_portuguesa_cuando_usted_nacio' => ['label' => '3. ¿Su padre o madre ya tenía la nacionalidad portuguesa cuando usted nació?'],
                'su_padre_o_madre_obtuvo_la_nacionalidad_portuguesa_despues_de_su_nacimiento' => ['label' => '¿Su padre o madre obtuvo la nacionalidad portuguesa después de su nacimiento?'],
                'n4_su_padremadre_hijoa_del_ciudadano_portugues_conserva_la_nacionalidad_portuguesa' => ['label' => '4. ¿Su padre, madre, hijo o hija del ciudadano portugués conserva la nacionalidad portuguesa?'],
                'n5_cuenta_con_documentos_que_permitan_demostrar_el_vinculo_familiar_con_el_ciudadano_portugues' => ['label' => '5. ¿Cuenta con documentos que permitan demostrar el vínculo familiar con el ciudadano portugués?'],
                'cuenta_con_las_partidas_de_nacimiento_que_acreditan_la_linea_familiar_hasta_el_ciudadano_portugues' => ['label' => '¿Cuenta con las partidas de nacimiento que acreditan la línea familiar hasta el ciudadano portugués?'],
                'tiene_la_partida_de_nacimiento_que_prueba_que_su_padremadre_es_hijoa_de_un_ciudadano_portugues' => ['label' => '¿Tiene la partida de nacimiento que prueba que su padre o madre es hijo o hija de un ciudadano portugués?'],
            ],
        ],
        'nacionalidad espanola para familiares' => [
            'service' => 'Nacionalidad Española para Familiares',
            'title' => 'Formulario 001 · Nacionalidad Española para Familiares',
            'form_id' => '5ab1cc74-b914-4f0f-aabb-7c61e11d0f0f',
            'fields' => [
                'n1_que_parentesco_tiene_con_la_persona_de_nacionalidad_espanola' => ['label' => '1. ¿Qué parentesco tiene con la persona de nacionalidad española?'],
                'n2_como_obtuvo_la_nacionalidad_espanola_su_familiar' => ['label' => '2. ¿Cómo obtuvo la nacionalidad española su familiar?'],
                'su_familiar_conserva_actualmente_la_nacionalidad_espanola' => ['label' => '¿Su familiar conserva actualmente la nacionalidad española?'],
                'su_familiar_reside_actualmente_en_espana' => ['label' => '¿Su familiar reside actualmente en España?'],
                'su_familiar_perdio_renuncio_o_recupero_en_algun_momento_la_nacionalidad_espanola' => ['label' => '¿Su familiar perdió, renunció o recuperó en algún momento la nacionalidad española?'],
                'n3_su_padre_o_madre_ya_tenia_la_nacionalidad_espanola_cuando_usted_nacio' => ['label' => '¿Su padre o madre ya tenía la nacionalidad española cuando usted nació?'],
                'su_padre_o_madre_obtuvo_la_nacionalidad_espanola_despues_de_su_nacimiento_clonada' => ['label' => '¿Su padre o madre obtuvo la nacionalidad española después de su nacimiento?'],
                'n4_su_padremadre_hijoa_del_ciudadano_portugues_conserva_la_nacionalidad_espanola' => ['label' => '¿Su padre, madre, hijo o hija del ciudadano español conserva la nacionalidad española?'],
                'n5_cuenta_con_documentos_que_permitan_demostrar_el_vinculo_familiar_con_el_ciudadano_espanol' => ['label' => '¿Cuenta con documentos que permitan demostrar el vínculo familiar con el ciudadano español?'],
                'cuenta_con_las_partidas_de_nacimiento_que_acreditan_la_linea_familiar_hasta_el_ciudadano_espanol' => ['label' => '¿Cuenta con las partidas de nacimiento que acreditan la línea familiar hasta el ciudadano español?'],
                'tiene_la_partida_de_nacimiento_que_prueba_que_su_padremadre_es_hijoa_de_un_ciudadano_espanol' => ['label' => '¿Tiene la partida de nacimiento que prueba que su padre o madre es hijo o hija de un ciudadano español?'],
            ],
        ],
        'nacionalidad espanola por conyuge' => [
            'service' => 'Nacionalidad Española por Cónyuge',
            'title' => 'Formulario 001 · Nacionalidad Española por Cónyuge',
            'form_id' => 'eafda353-6a99-419d-aa6e-221e2c880a46',
            'fields' => [
                'n1_que_parentesco_tiene_con_la_persona_de_nacionalidad_espanola' => ['label' => '1. ¿Qué parentesco tiene con la persona de nacionalidad española?'],
                'que_parentesco_tiene_con_la_persona_de_nacionalidad_espanola' => ['label' => '¿Qué parentesco tiene con la persona de nacionalidad española?'],
                'n2_como_obtuvo_la_nacionalidad_espanola_su_familiar' => ['label' => '2. ¿Cómo obtuvo la nacionalidad española su familiar?'],
                'su_familiar_conserva_actualmente_la_nacionalidad_espanola' => ['label' => '¿Su familiar conserva actualmente la nacionalidad española?'],
                'su_familiar_reside_actualmente_en_espana' => ['label' => '¿Su familiar reside actualmente en España?'],
                'su_familiar_perdio_renuncio_o_recupero_en_algun_momento_la_nacionalidad_espanola' => ['label' => '¿Su familiar perdió, renunció o recuperó en algún momento la nacionalidad española?'],
                'que_ocurrio_con_la_nacionalidad_espanola_de_su_familiar' => ['label' => '¿Qué ocurrió con la nacionalidad española de su familiar?'],
                'n3_su_padre_o_madre_ya_tenia_la_nacionalidad_espanola_cuando_usted_nacio' => ['label' => '3. ¿Su padre o madre ya tenía la nacionalidad española cuando usted nació?'],
                'su_padre_o_madre_obtuvo_la_nacionalidad_espanola_despues_de_su_nacimiento_clonada' => ['label' => '¿Su padre o madre obtuvo la nacionalidad española después de su nacimiento?'],
                'n4_su_padremadre_hijoa_del_ciudadano_portugues_conserva_la_nacionalidad_espanola' => ['label' => '4. ¿Su padre, madre, hijo o hija del ciudadano español conserva la nacionalidad española?'],
                'n5_cuenta_con_documentos_que_permitan_demostrar_el_vinculo_familiar_con_el_ciudadano_espanol' => ['label' => '5. ¿Cuenta con documentos que permitan demostrar el vínculo familiar con el ciudadano español?'],
                'cuenta_con_las_partidas_de_nacimiento_que_acreditan_la_linea_familiar_hasta_el_ciudadano_espanol' => ['label' => '¿Cuenta con las partidas de nacimiento que acreditan la línea familiar hasta el ciudadano español?'],
                'tiene_la_partida_de_nacimiento_que_prueba_que_su_padremadre_es_hijoa_de_un_ciudadano_espanol' => ['label' => '¿Tiene la partida de nacimiento que prueba que su padre o madre es hijo o hija de un ciudadano español?'],
            ],
        ],
    ];

    public function __construct()
    {
        $this->hubspot = $this->makeHubspotClient();
    }

    private function makeHubspotClient()
    {
        return Factory::createWithAccessToken(
            env('HUBSPOT_KEY'),
            $this->makeHttpClient()
        );
    }

    private function makeHttpClient(array $options = []): ClientInterface
    {
        return new GuzzleClient(array_merge([
            'verify' => $this->hubspotCaBundle(),
            'force_ip_resolve' => $this->hubspotIpResolve(),
            'connect_timeout' => 10,
            'timeout' => 30,
        ], $options));
    }

    private function hubspotIpResolve(): string
    {
        $mode = strtolower((string) env('HUBSPOT_FORCE_IP_RESOLVE', 'v4'));

        return in_array($mode, ['v4', 'v6'], true) ? $mode : 'v4';
    }

    private function hubspotCaBundle()
    {
        $paths = array_filter([
            env('HUBSPOT_CA_BUNDLE'),
            ini_get('curl.cainfo') ?: null,
            ini_get('openssl.cafile') ?: null,
            base_path('storage/certs/cacert.pem'),
            'C:\\xampp\\php\\extras\\ssl\\cacert.pem',
            'C:\\xampp\\phpMyAdmin\\vendor\\composer\\ca-bundle\\res\\cacert.pem',
            'C:\\xampp\\perl\\vendor\\lib\\Mozilla\\CA\\cacert.pem',
        ]);

        foreach ($paths as $path) {
            if (is_string($path) && file_exists($path)) {
                return $path;
            }
        }

        return true;
    }

    /**
     * Reads property definitions only. This intentionally does not fetch any
     * Contact or Deal records, so it is safe to use from the audit catalogue.
     *
     * @return array<int, array{key:string,label:string,type:?string,field_type:?string,group:?string,description:?string}>
     */
    public function propertyCatalog(string $objectType): array
    {
        if (! in_array($objectType, ['contacts', 'deals'], true)) {
            throw new \InvalidArgumentException('El catálogo de HubSpot solo admite Contacts o Deals.');
        }

        try {
            $properties = $this->hubspot
                ->crm()
                ->properties()
                ->coreApi()
                ->getAll($objectType)
                ->getResults();

            return collect($properties)
                ->map(function ($property): array {
                    $read = static function ($object, string $method): ?string {
                        if (! method_exists($object, $method)) {
                            return null;
                        }

                        $value = $object->{$method}();

                        return $value === null || $value === '' ? null : (string) $value;
                    };

                    return [
                        // HubSpot identifies a property by its API name.
                        'key' => (string) $property->getName(),
                        'label' => $read($property, 'getLabel') ?: (string) $property->getName(),
                        'type' => $read($property, 'getType'),
                        'field_type' => $read($property, 'getFieldType'),
                        'group' => $read($property, 'getGroupName'),
                        'description' => $read($property, 'getDescription'),
                    ];
                })
                ->filter(fn (array $property): bool => $property['key'] !== '')
                ->unique('key')
                ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
                ->values()
                ->all();
        } catch (PropertiesApiException $exception) {
            throw new \RuntimeException('HubSpot no permitió leer su catálogo de propiedades (HTTP '.$exception->getCode().'): '.$exception->getMessage(), 0, $exception);
        } catch (\Throwable $exception) {
            throw new \RuntimeException('No fue posible leer el catálogo de propiedades de HubSpot: '.$exception->getMessage(), 0, $exception);
        }
    }

    public function getAllContactsByOwnerId(
        string $ownerId,
        array $properties = ['email', 'firstname', 'lastname', 'hubspot_owner_id'],
        int $limit = 100
    ): array {
        $all = [];
        $after = null;

        try {
            do {
                $filter = new Filter();
                $filter
                    ->setOperator('EQ')
                    ->setPropertyName('hubspot_owner_id')
                    ->setValue($ownerId);

                $filterGroup = new FilterGroup();
                $filterGroup->setFilters([$filter]);

                $searchRequest = new PublicObjectSearchRequest();
                $searchRequest->setFilterGroups([$filterGroup]);
                $searchRequest->setProperties($properties);
                $searchRequest->setLimit($limit);

                if (!is_null($after)) {
                    $searchRequest->setAfter($after);
                }

                $page = $this->hubspot->crm()->contacts()->searchApi()->doSearch($searchRequest);

                foreach ($page->getResults() as $contact) {
                    $all[] = [
                        'id' => $contact->getId(),
                        'properties' => $contact->getProperties(),
                    ];
                }

                // Paginación
                $paging = $page->getPaging();
                $after = ($paging && $paging->getNext())
                    ? $paging->getNext()->getAfter()
                    : null;

            } while (!is_null($after));

            return $all;

        } catch (\Exception $e) {
            throw new \Exception("Error al obtener contactos por ownerId en HubSpot: " . $e->getMessage());
        }
    }

    public function check001(string $hsId, int $maxRetries = 100, int $sleepSeconds = 2): ?array
    {
        $contactData = null;

        for ($i = 0; $i < $maxRetries; $i++) {
            try {
                $contactData = $this->getContactById($hsId);

                if ($contactData) {
                    $props = $contactData['properties'] ?? [];

                    // Checar campo de archivo "pasaporte__documento_"
                    if (!empty($props['pasaporte__documento_'])) {
                        // Contacto encontrado y con el archivo cargado
                        return $contactData;
                    }
                }
            } catch (\Exception $e) {
                \Log::warning("HubSpot check001 intento $i fallo: " . $e->getMessage());
            }

            sleep($sleepSeconds);
        }

        return null; // no se encontró en el tiempo esperado
    }

    public function createContact(array $properties): ?string
    {
        try {
            // Crear el objeto de entrada para la API de HubSpot
            $contactInput = new SimplePublicObjectInput([
                'properties' => array_filter($properties, fn($value) => !is_null($value)) // Filtra valores nulos
            ]);

            // Enviar solicitud para crear el contacto
            $response = $this->hubspot->crm()->contacts()->basicApi()->create($contactInput);

            // Retornar el ID del contacto creado
            return $response->getId();
        } catch (ContactException $e) {
            // Loguear el error para depuración
            \Log::error('Error al crear contacto en HubSpot: ' . $e->getMessage(), [
                'response' => $e->getResponseBody(),
                'code' => $e->getCode(),
                'properties' => $properties
            ]);
            throw new \Exception('No se pudo crear el contacto en HubSpot: ' . $e->getMessage());
        }
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

    public function getContactOwnerById($id): ?array
    {
        try {
            $this->hubspotThrottle();

            $contact = $this->hubspot
                ->crm()
                ->contacts()
                ->basicApi()
                ->getById((string) $id, ['email', 'hubspot_owner_id']);

            return [
                'id' => $contact->getId(),
                'properties' => $contact->getProperties(),
            ];
        } catch (ContactException $e) {
            throw new \Exception('Error al obtener owner del contacto en HubSpot: ' . $e->getMessage());
        }
    }

    /**
     * Devuelve solo los Formularios 001 vinculados a servicios pagados.
     */
    public function formulario001ForUser(User $user, bool $includeResponses = true): array
    {
        $dealServiceNames = Negocio::query()
            ->where('user_id', $user->id)
            ->get()
            ->flatMap(fn (Negocio $negocio): array => [
                $negocio->servicio_solicitado2,
                $negocio->servicio_solicitado,
            ])
            ->map(fn ($service): string => trim((string) $service))
            ->filter();

        $invoiceHashes = Factura::query()
            ->where('id_cliente', $user->id)
            ->pluck('hash_factura')
            ->filter();

        $purchases = Compras::query()
            ->with('servicio:id,nombre')
            ->where(function ($query) use ($user, $invoiceHashes): void {
                $query->where(function ($paidPurchases) use ($user): void {
                    $paidPurchases->where('id_user', $user->id)->where('pagado', 1);
                });

                if ($invoiceHashes->isNotEmpty()) {
                    $query->orWhereIn('hash_factura', $invoiceHashes);
                }
            })
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->get();

        $servicesByHubSpotId = Servicio::query()
            ->whereIn('id_hubspot', $purchases->pluck('servicio_hs_id')->filter()->unique())
            ->pluck('nombre', 'id_hubspot');

        $purchaseServiceNames = $purchases
            ->map(function (Compras $purchase) use ($servicesByHubSpotId): string {
                return trim((string) ($purchase->servicio?->nombre
                    ?: $servicesByHubSpotId->get($purchase->servicio_hs_id)
                    ?: $purchase->servicio_hs_id));
            })
            ->filter();

        $serviceNames = collect($dealServiceNames)
            ->merge($purchaseServiceNames)
            ->push((string) $user->servicio)
            ->filter();

        $definitions = [];
        $usingDefault = false;

        foreach ($serviceNames as $serviceName) {
            $serviceKey = $this->formulario001ServiceKey($serviceName);

            if (isset(self::FORMULARIO_001_FORMS[$serviceKey])) {
                $definitions[$serviceKey] = self::FORMULARIO_001_FORMS[$serviceKey];
            }
        }

        if ($definitions === []) {
            $definitions['default'] = self::FORMULARIO_001_DEFAULT_FORM;
            $usingDefault = true;
        }

        $forms = array_map(function (array $definition): array {
            return [
                'service' => $definition['service'],
                'title' => $definition['title'],
                'form_id' => $definition['form_id'],
                'fields' => $this->formulario001Fields($definition),
            ];
        }, array_values($definitions));

        if (! $includeResponses) {
            return [
                'status' => 'ok',
                'forms' => $forms,
                'using_default' => $usingDefault,
            ];
        }

        if (blank($user->hs_id)) {
            return [
                'status' => 'missing_contact',
                'forms' => $forms,
                'using_default' => $usingDefault,
            ];
        }

        $propertyNames = collect($forms)
            ->flatMap(fn (array $form): array => array_column($form['fields'], 'name'))
            ->unique()
            ->values()
            ->all();

        try {
            $this->hubspotThrottle();

            $contact = $this->hubspot
                ->crm()
                ->contacts()
                ->basicApi()
                ->getById((string) $user->hs_id, $propertyNames);
            $properties = $contact->getProperties();

            foreach ($forms as &$form) {
                foreach ($form['fields'] as &$field) {
                    $field['value'] = trim((string) ($properties[$field['name']] ?? ''));
                }
                unset($field);
            }
            unset($form);

            return [
                'status' => 'ok',
                'forms' => $forms,
                'using_default' => $usingDefault,
            ];
        } catch (\Throwable $exception) {
            Log::warning('No se pudieron obtener las respuestas del Formulario 001.', [
                'hubspot_contact_id' => $user->hs_id,
                'client_id' => $user->id,
                'exception' => $exception->getMessage(),
            ]);

            return [
                'status' => 'unavailable',
                'forms' => $forms,
                'using_default' => $usingDefault,
            ];
        }
    }

    private function formulario001Fields(array $definition): array
    {
        return collect(array_merge(self::FORMULARIO_001_COMMON_FIELDS, $definition['fields']))
            ->map(function (array $field, string $name): array {
                return [
                    'name' => $name,
                    'label' => $field['label'],
                    'type' => $field['type'] ?? 'text',
                    'value' => '',
                ];
            })
            ->values()
            ->all();
    }

    private function formulario001ServiceKey(?string $serviceName): string
    {
        $asciiName = Str::ascii((string) $serviceName);

        $normalizedName = strtolower(trim(preg_replace('/\s+/', ' ', $asciiName) ?: ''));

        return Str::startsWith($normalizedName, 'nacionalidad ')
            ? $normalizedName
            : 'nacionalidad '.$normalizedName;
    }

    /**
     * Obtiene las respuestas actuales del Formulario 001 de un contacto.
     */
    public function formulario001ForContact(?string $contactId): array
    {
        $fields = collect(self::FORMULARIO_001_COMMON_FIELDS)
            ->map(function (array $definition, string $name): array {
                return [
                    'name' => $name,
                    'label' => $definition['label'],
                    'type' => $definition['type'] ?? 'text',
                    'value' => '',
                ];
            })
            ->values()
            ->all();

        if (blank($contactId)) {
            return ['status' => 'missing_contact', 'fields' => $fields];
        }

        try {
            $this->hubspotThrottle();

            $contact = $this->hubspot
                ->crm()
                ->contacts()
                ->basicApi()
                ->getById((string) $contactId, array_keys(self::FORMULARIO_001_COMMON_FIELDS));
            $properties = $contact->getProperties();

            foreach ($fields as &$field) {
                $field['value'] = trim((string) ($properties[$field['name']] ?? ''));
            }
            unset($field);

            return ['status' => 'ok', 'fields' => $fields];
        } catch (\Throwable $exception) {
            Log::warning('No se pudieron obtener las respuestas del Formulario 001.', [
                'hubspot_contact_id' => $contactId,
                'exception' => $exception->getMessage(),
            ]);

            return ['status' => 'unavailable', 'fields' => $fields];
        }
    }

    public function searchContactOwnerByEmail($email): ?array
    {
        try {
            $this->hubspotThrottle();

            $filter = new Filter();
            $filter
                ->setOperator('EQ')
                ->setPropertyName('email')
                ->setValue($email);

            $filterGroup = new FilterGroup();
            $filterGroup->setFilters([$filter]);

            $searchRequest = new PublicObjectSearchRequest();
            $searchRequest->setFilterGroups([$filterGroup]);
            $searchRequest->setProperties(['email', 'hubspot_owner_id']);
            $searchRequest->setLimit(1);

            $contactsPage = $this->hubspot->crm()->contacts()->searchApi()->doSearch($searchRequest);

            if (count($contactsPage->getResults()) === 0) {
                return null;
            }

            $contact = $contactsPage->getResults()[0];

            return [
                'id' => $contact->getId(),
                'properties' => $contact->getProperties(),
            ];
        } catch (ContactException $e) {
            throw new \Exception('Error al buscar owner del contacto en HubSpot: ' . $e->getMessage());
        }
    }

    public function createUser(array $payload): array
    {
        try {
            $this->hubspotThrottle();

            $userProvisionRequest = new UserProvisionRequest([
                'email' => (string) ($payload['email'] ?? ''),
                'role_id' => $payload['roleId'] ?? $payload['role_id'] ?? null,
                'primary_team_id' => $payload['primaryTeamId'] ?? $payload['primary_team_id'] ?? null,
                'secondary_team_ids' => $payload['secondaryTeamIds'] ?? $payload['secondary_team_ids'] ?? null,
                'send_welcome_email' => (bool) ($payload['sendWelcomeEmail'] ?? $payload['send_welcome_email'] ?? true),
            ]);

            if (! $userProvisionRequest->valid()) {
                throw new \InvalidArgumentException(
                    'Payload invalido para crear usuario HubSpot: ' .
                    implode(', ', $userProvisionRequest->listInvalidProperties())
                );
            }

            $createdUser = $this->hubspot
                ->settings()
                ->users()
                ->usersApi()
                ->create($userProvisionRequest);

            return [
                'id' => $createdUser->getId(),
                'email' => $createdUser->getEmail(),
                'roleId' => $createdUser->getRoleId(),
                'primaryTeamId' => $createdUser->getPrimaryTeamId(),
                'secondaryTeamIds' => $createdUser->getSecondaryTeamIds(),
            ];
        } catch (UsersApiException $e) {
            $body = $e->getResponseBody();
            $body = is_scalar($body)
                ? (string) $body
                : json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            throw new \RuntimeException("Error creando usuario en HubSpot ({$e->getCode()}): {$body}", $e->getCode(), $e);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Error creando usuario en HubSpot: ' . $e->getMessage(), 0, $e);
        }
    }

    public function findUserByEmail(string $email): ?array
    {
        $email = strtolower(trim($email));

        if ($email === '') {
            return null;
        }

        try {
            $this->hubspotThrottle();

            $hubspotUser = $this->hubspot
                ->settings()
                ->users()
                ->usersApi()
                ->getById($email, 'EMAIL');

            return [
                'id' => $hubspotUser->getId(),
                'email' => $hubspotUser->getEmail(),
                'roleId' => $hubspotUser->getRoleId(),
                'primaryTeamId' => $hubspotUser->getPrimaryTeamId(),
                'secondaryTeamIds' => $hubspotUser->getSecondaryTeamIds(),
            ];
        } catch (UsersApiException $e) {
            if ((int) $e->getCode() === 404) {
                return null;
            }

            $body = $e->getResponseBody();
            $body = is_scalar($body)
                ? (string) $body
                : json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            throw new \RuntimeException("Error buscando usuario en HubSpot ({$e->getCode()}): {$body}", $e->getCode(), $e);
        }
    }

    public function findOwnerByEmail(string $email, bool $includeArchived = false): ?array
    {
        $email = strtolower(trim($email));

        if ($email === '') {
            return null;
        }

        try {
            $this->hubspotThrottle();

            $response = $this->hubspot->apiRequest([
                'method' => 'GET',
                'path' => '/crm/v3/owners',
                'qs' => [
                    'email' => $email,
                    'archived' => $includeArchived ? 'true' : 'false',
                    'limit' => 100,
                ],
            ]);

            $body = json_decode((string) $response->getBody(), true) ?: [];
            $owners = $body['results'] ?? [];

            foreach ($owners as $owner) {
                if (strtolower(trim((string) ($owner['email'] ?? ''))) === $email) {
                    return $owner;
                }
            }

            return null;
        } catch (RequestException $e) {
            $statusCode = $e->getResponse()?->getStatusCode() ?: 0;
            $body = $e->getResponse() ? (string) $e->getResponse()->getBody() : $e->getMessage();

            throw new \RuntimeException("Error buscando owner en HubSpot ({$statusCode}): {$body}", $statusCode, $e);
        }
    }

    public function getOwnerById(string $ownerId): ?array
    {
        $ownerId = trim($ownerId);

        if ($ownerId === '') {
            return null;
        }

        try {
            $this->hubspotThrottle();

            $response = $this->hubspot->apiRequest([
                'method' => 'GET',
                'path' => "/crm/v3/owners/{$ownerId}",
            ]);

            return json_decode((string) $response->getBody(), true) ?: null;
        } catch (RequestException $e) {
            if ((int) ($e->getResponse()?->getStatusCode() ?: 0) === 404) {
                return null;
            }

            $statusCode = $e->getResponse()?->getStatusCode() ?: 0;
            $body = $e->getResponse() ? (string) $e->getResponse()->getBody() : $e->getMessage();

            throw new \RuntimeException("Error obteniendo owner en HubSpot ({$statusCode}): {$body}", $statusCode, $e);
        }
    }

    public function createTicket(array $properties, ?string $contactId = null): array
    {
        try {
            $this->hubspotThrottle();

            $payload = [
                'properties' => array_filter($properties, fn ($value) => $value !== null && $value !== ''),
            ];

            if ($contactId) {
                $payload['associations'] = [
                    [
                        'to' => ['id' => $contactId],
                        'types' => [
                            [
                                'associationCategory' => 'HUBSPOT_DEFINED',
                                'associationTypeId' => 16,
                            ],
                        ],
                    ],
                ];
            }

            $response = $this->hubspot->apiRequest([
                'method' => 'POST',
                'path' => '/crm/v3/objects/tickets',
                'body' => $payload,
            ]);

            return json_decode((string) $response->getBody(), true) ?: [];
        } catch (RequestException $e) {
            $statusCode = $e->getResponse()?->getStatusCode() ?: 0;
            $body = $e->getResponse() ? (string) $e->getResponse()->getBody() : $e->getMessage();

            throw new \RuntimeException("Error creando ticket en HubSpot ({$statusCode}): {$body}", $statusCode, $e);
        }
    }

    public function getDefaultTicketPipelineStage(): array
    {
        return $this->getTicketPipelineStage();
    }

    public function getTicketPipelineStage(?string $preferredPipelineId = null, ?string $preferredStageId = null): array
    {
        $preferredPipelineId = trim((string) $preferredPipelineId);
        $preferredStageId = trim((string) $preferredStageId);

        $pipelines = collect($this->getTicketPipelines())
            ->filter(fn ($pipeline) => ! ($pipeline['archived'] ?? false))
            ->sortBy('displayOrder')
            ->values();

        if ($pipelines->isEmpty()) {
            throw new \RuntimeException('HubSpot no devolvio pipelines activos de tickets.');
        }

        if ($preferredPipelineId !== '') {
            $pipeline = $pipelines->first(fn ($pipeline) => (string) ($pipeline['id'] ?? '') === $preferredPipelineId);

            if ($pipeline) {
                $stage = $this->chooseTicketStage($pipeline, $preferredStageId);

                if (! $stage) {
                    throw new \RuntimeException('HubSpot no devolvio etapas activas para el pipeline de tickets.');
                }

                return [
                    'hs_pipeline' => (string) $pipeline['id'],
                    'hs_pipeline_stage' => (string) $stage['id'],
                ];
            }
        }

        $pipeline = $pipelines->first();
        $stage = $this->chooseTicketStage($pipeline);

        if (! $stage) {
            throw new \RuntimeException('HubSpot no devolvio etapas activas para el pipeline de tickets.');
        }

        return [
            'hs_pipeline' => (string) $pipeline['id'],
            'hs_pipeline_stage' => (string) $stage['id'],
        ];
    }

    private function getTicketPipelines(): array
    {
        return Cache::remember('hubspot_ticket_pipelines', 3600, function () {
            try {
                $this->hubspotThrottle();

                $response = $this->hubspot->apiRequest([
                    'method' => 'GET',
                    'path' => '/crm/v3/pipelines/tickets',
                ]);

                $body = json_decode((string) $response->getBody(), true) ?: [];

                return $body['results'] ?? [];
            } catch (RequestException $e) {
                $statusCode = $e->getResponse()?->getStatusCode() ?: 0;
                $body = $e->getResponse() ? (string) $e->getResponse()->getBody() : $e->getMessage();

                throw new \RuntimeException("Error obteniendo pipeline de tickets en HubSpot ({$statusCode}): {$body}", $statusCode, $e);
            }
        });
    }

    private function chooseTicketStage(array $pipeline, ?string $preferredStageId = null): ?array
    {
        $preferredStageId = trim((string) $preferredStageId);
        $stages = collect($pipeline['stages'] ?? [])
            ->filter(fn ($stage) => ! ($stage['archived'] ?? false))
            ->sortBy('displayOrder')
            ->values();

        if ($stages->isEmpty()) {
            return null;
        }

        if ($preferredStageId !== '') {
            $stage = $stages->first(fn ($stage) => (string) ($stage['id'] ?? '') === $preferredStageId);

            if ($stage) {
                return $stage;
            }
        }

        return $stages->first(function ($stage) {
            return strtoupper((string) ($stage['metadata']['ticketState'] ?? '')) === 'OPEN';
        }) ?: $stages->first();
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
            $this->hubspotThrottle();

            $properties = HubspotContactPropertyNormalizer::normalize($properties);

            $input = new SimplePublicObjectInput([
                'properties' => $properties,
            ]);

            return $this->hubspot->crm()->contacts()->basicApi()->update($hsId, $input);
        } catch (ContactException $e) {
            $statusCode   = $e->getCode();
            $responseBody = $e->getResponseBody();

            \Log::error('Error actualizando contacto en HubSpot', [
                'hs_id' => $hsId,
                'status_code' => $statusCode,
                'response' => $responseBody,
                'properties' => $properties,
            ]);

            throw new \Exception("Error actualizando contacto {$hsId} en HubSpot ({$statusCode}): {$responseBody}", 0, $e);
        }
    }

    public function batchUpdateContactOwners(array $contactIds, string $hubspotOwnerId, int $chunkSize = 100): int
    {
        $contactIds = array_values(array_unique(array_filter(array_map('strval', $contactIds))));
        $updated = 0;

        foreach (array_chunk($contactIds, $chunkSize) as $chunk) {
            $this->hubspotThrottle();

            $input = new ContactBatchInput([
                'inputs' => array_map(
                    fn ($id) => new ContactBatchObjectInput([
                        'id' => $id,
                        'properties' => [
                            'hubspot_owner_id' => $hubspotOwnerId,
                        ],
                    ]),
                    $chunk
                ),
            ]);

            $this->hubspot->crm()->contacts()->batchApi()->update($input);
            $updated += count($chunk);
        }

        return $updated;
    }

    public function getDealById(string $dealId): ?array
    {
        try {
            $properties = $this->getDealProperties();

            $batchRequest = new BatchReadInputSimplePublicObjectId([
                'properties' => $properties,
                'inputs' => [
                    ['id' => $dealId],
                ],
            ]);

            $response = $this->hubspot->crm()->deals()->batchApi()->read($batchRequest);

            if (count($response->getResults()) > 0) {
                $deal = $response->getResults()[0];
                $allProperties = $deal->getProperties();

                // Filtrar propiedades que no comiencen con "hs_"
                $filteredProperties = array_filter(
                    $allProperties,
                    fn($key) => strpos($key, 'hs_') !== 0,
                    ARRAY_FILTER_USE_KEY
                );

                return [
                    'id' => $deal->getId(),
                    'properties' => $filteredProperties,
                ];
            }

            return null;

        } catch (DealException $e) {
            throw new \Exception("Error al obtener el deal por ID en HubSpot: " . $e->getMessage());
        } catch (\Exception $e) {
            throw new \Exception("Error general al obtener el deal por ID: " . $e->getMessage());
        }
    }

    public function updateDeals($hsId, $properties)
    {
        try {
            // Primer intento
            $this->hubspot->crm()->deals()->basicApi()->update($hsId, [
                'properties' => $properties
            ]);
        } catch (DealException $e) {

            // ===========================================================
            // 🔍 CAPTURAR RESPUESTA COMPLETA
            // ===========================================================
            $rawBody = (string) $e->getResponseBody();

            if (property_exists($e, 'response') && $e->response instanceof Response) {
                $rawBody = (string) $e->response->getBody();
            }

            $decoded = json_decode($rawBody, true);
            $statusCode = $e->getCode();

            \Log::error("❌ HubSpot update error ({$statusCode}): " . $rawBody);

            // ===========================================================
            // 🧩 ELIMINAR TODAS LAS PROPIEDADES INVÁLIDAS Y REINTENTAR 1 VEZ
            // ===========================================================
            if (!empty($decoded['message']) && str_contains($decoded['message'], 'Property values were not valid')) {

                // Detecta TODAS las propiedades conflictivas en una sola pasada
                preg_match_all('/"([a-zA-Z0-9_]+)" was not one of the allowed options/', $decoded['message'], $matches);
                $invalidProps = array_unique($matches[1] ?? []);

                if (!empty($invalidProps)) {
                    foreach ($invalidProps as $prop) {
                        unset($properties[$prop]);
                    }

                    \Log::warning("⚠️ Removed invalid HubSpot properties: " . implode(', ', $invalidProps));

                    // Solo un segundo intento
                    try {
                        $this->hubspot->crm()->deals()->basicApi()->update($hsId, [
                            'properties' => $properties
                        ]);
                        \Log::info("✅ HubSpot update successful after cleaning invalid properties.");
                    } catch (DealException $retryException) {
                        $retryRaw = (string) $retryException->getResponseBody();
                        if (property_exists($retryException, 'response') && $retryException->response instanceof Response) {
                            $retryRaw = (string) $retryException->response->getBody();
                        }
                        \Log::error("❌ HubSpot retry failed: " . $retryRaw);
                    }
                }
            }
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

    public function getDealIdsByContactId(string $contactId): array
    {
        try {
            $this->hubspotThrottle();

            $associations = $this->hubspot->crm()->associations()->batchApi()->read(
                'contacts',
                'deals',
                new BatchInputPublicObjectId([
                    'inputs' => [
                        ['id' => $contactId],
                    ],
                ])
            );

            $dealIds = [];
            foreach ($associations->getResults() as $association) {
                foreach ($association->getTo() as $toItem) {
                    $dealIds[] = (string) $toItem->getId();
                }
            }

            return array_values(array_unique($dealIds));
        } catch (\Exception $e) {
            throw new \Exception('Error al obtener IDs de negocios asociados al contacto: ' . $e->getMessage());
        }
    }

    public function updateDealOwnersByContactId(string $contactId, string $hubspotOwnerId): int
    {
        $dealIds = $this->getDealIdsByContactId($contactId);

        if (empty($dealIds)) {
            return 0;
        }

        return $this->batchUpdateDealOwners($dealIds, $hubspotOwnerId);
    }

    public function getDealIdsByContactIds(array $contactIds, int $chunkSize = 100): array
    {
        $contactIds = array_values(array_unique(array_filter(array_map('strval', $contactIds))));
        $dealIds = [];

        foreach (array_chunk($contactIds, $chunkSize) as $chunk) {
            $associations = $this->hubspot->crm()->associations()->batchApi()->read(
                'contacts',
                'deals',
                new BatchInputPublicObjectId([
                    'inputs' => array_map(fn ($id) => ['id' => $id], $chunk),
                ])
            );

            foreach ($associations->getResults() as $association) {
                foreach ($association->getTo() as $toItem) {
                    $dealIds[] = (string) $toItem->getId();
                }
            }
        }

        return array_values(array_unique($dealIds));
    }

    public function batchUpdateDealOwners(array $dealIds, string $hubspotOwnerId, int $chunkSize = 100): int
    {
        $dealIds = array_values(array_unique(array_filter(array_map('strval', $dealIds))));
        $updated = 0;

        foreach (array_chunk($dealIds, $chunkSize) as $chunk) {
            $this->hubspotThrottle();

            $input = new DealBatchInput([
                'inputs' => array_map(
                    fn ($id) => new DealBatchObjectInput([
                        'id' => $id,
                        'properties' => [
                            'hubspot_owner_id' => $hubspotOwnerId,
                        ],
                    ]),
                    $chunk
                ),
            ]);

            $this->hubspot->crm()->deals()->batchApi()->update($input);
            $updated += count($chunk);
        }

        return $updated;
    }

    public function getDealsByContactId(string $contactId): array
    {
        try {
            // 1. Obtener todas las propiedades de "deals"
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

            // 6. Retornamos un array con la información filtrada de cada deal
            return array_map(function ($deal) {
                $allProperties = $deal->getProperties();

                // Filtrar propiedades que no comiencen con "hs_"
                $filteredProperties = array_filter(
                    $allProperties,
                    fn($key) => strpos($key, 'hs_') !== 0,
                    ARRAY_FILTER_USE_KEY
                );

                return [
                    'id' => $deal->getId(),
                    'properties' => $filteredProperties,
                ];
            }, $dealsResponse->getResults());

        } catch (\Exception $e) {
            throw new \Exception('Error al obtener los negocios asociados al contacto: ' . $e->getMessage());
        }
    }

    public function getDealStagesByPipeline(string $pipelineId): array
    {
        try {
            // Obtener el pipeline con sus etapas
            $pipeline = $this->hubspot->crm()->pipelines()->pipelinesApi()->getById('deals', $pipelineId);

            // Extraer las etapas (dealstages) del pipeline
            $stages = $pipeline->getStages();

            // Retornar un arreglo con los IDs y nombres de las etapas
            return array_map(function ($stage) {
                return [
                    'id' => $stage->getId(),
                    'name' => $stage->getLabel(),
                ];
            }, $stages);
        } catch (\Exception $e) {
            throw new \Exception('Error al obtener las etapas del pipeline: ' . $e->getMessage());
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
            $legacyClient = $this->makeHttpClient([
                'base_uri' => 'https://api.hubapi.com',
            ]);
            $token = env('HUBSPOT_KEY');

            // 3. Cliente v3 de archivos (File Manager)
            $filesClient = $this->makeHubspotClient();

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
            $hubspot  = $this->makeHubspotClient();

            // 2. Obtener el contacto con las propiedades que nos interesan
            //    - Si tienes propiedades: ['mi_archivo_cv', 'foto_del_contacto', ...]
            //      pásalas en el segundo parámetro de getById con implode()
            //    - Ejemplo: "mi_archivo_cv,foto_del_contacto"

            $fileProperties = ["pasaporte__documento_", "partida_de_nacimiento_simple__", "documentos_adicionales"];
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
            $fileDetails = $hubspot->files()->filesApi()->getById($fileId);

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

    private function hubspotThrottle(): void
    {
        $milliseconds = (int) env('HUBSPOT_TASKS_THROTTLE_MS', 250);

        if ($milliseconds > 0) {
            usleep($milliseconds * 1000);
        }
    }

    /**
     * Buscar un contacto por número de pasaporte.
     */
    public function searchContactByPassport($passportNumber)
    {
        try {
            $filter = new \HubSpot\Client\Crm\Contacts\Model\Filter();
            $filter
                ->setOperator('EQ')
                ->setPropertyName('numero_de_pasaporte')
                ->setValue($passportNumber);

            $filterGroup = new \HubSpot\Client\Crm\Contacts\Model\FilterGroup();
            $filterGroup->setFilters([$filter]);

            $searchRequest = new \HubSpot\Client\Crm\Contacts\Model\PublicObjectSearchRequest();
            $searchRequest->setFilterGroups([$filterGroup]);
            // Asegúrate de incluir todas las propiedades que necesitas
            $searchRequest->setProperties(['email', 'firstname', 'lastname', 'numero_de_pasaporte']);
            $searchRequest->setLimit(1);

            $contactsPage = $this->hubspot->crm()->contacts()->searchApi()->doSearch($searchRequest);

            if (count($contactsPage->getResults()) > 0) {
                $contact = $contactsPage->getResults()[0];
                $properties = $contact->getProperties();

                return [
                    'id' => $contact->getId(),
                    'properties' => [
                        'email' => $properties['email'] ?? null,
                        'firstname' => $properties['firstname'] ?? null,
                        'lastname' => $properties['lastname'] ?? null,
                        'numero_de_pasaporte' => $properties['numero_de_pasaporte'] ?? null
                    ]
                ];
            }

            return null;
        } catch (ContactException $e) {
            throw new \Exception('Error al buscar el contacto por pasaporte en HubSpot: ' . $e->getMessage());
        }
    }

    public function createDealInHubspotFromTL(array $teamleaderDeal, string $hsContactId, array $camposRelacionados): ?array
    {
        try {
            $dealProperties = [];

            // Nombre del trato
            $dealProperties['dealname'] = $teamleaderDeal['title'] ?? 'Sin título';

            // Monto del proyecto
            if (isset($teamleaderDeal['budget']['amount'])) {
                $dealProperties['amount'] = $teamleaderDeal['budget']['amount'];
            }

            // Fecha de creación
            if (isset($teamleaderDeal['starts_on'])) {
                $dealProperties['createdate'] = (new \DateTime($teamleaderDeal['starts_on']))->format(\DateTime::ATOM);
            }

            // Mapear campos personalizados de TL a HS
            if (isset($teamleaderDeal['custom_fields'])) {
                foreach ($teamleaderDeal['custom_fields'] as $customField) {
                    $tlFieldId = $customField['definition']['id'] ?? null;
                    $value = $customField['value'] ?? null;

                    if ($tlFieldId && $value) {
                        $hsField = array_search($tlFieldId, $camposRelacionados, true);
                        if ($hsField) {
                            $dealProperties[$hsField] = $value;
                        }
                    }
                }
            }

            // Crear el trato (deal) en HubSpot
            $response = $this->hubspot->crm()->deals()->basicApi()->create([
                'properties' => $dealProperties
            ]);

            $dealId = $response->getId();

            // Asociar el nuevo trato con el contacto
            $this->hubspot->crm()->associations()->basicApi()->create(
                'deals',
                $dealId,
                'contacts',
                $hsContactId,
                [
                    'associationTypeId' => 3 // Contact to deal association
                ]
            );

            return [
                'id' => $dealId,
                'properties' => $dealProperties
            ];
        } catch (\Exception $e) {
            throw new \Exception('Error al crear el trato en HubSpot desde Teamleader: ' . $e->getMessage());
        }
    }

    public function getContactByIdPromise($contactId)
    {
        return Promise\Create::promiseFor($this->getContactById($contactId));
    }

    public function getEngagementsByContactIdPromise($contactId)
    {
        return Promise\Create::promiseFor($this->getEngagementsByContactId($contactId));
    }

    public function getDealsByContactIdPromise($contactId)
    {
        return Promise\Create::promiseFor($this->getDealsByContactId($contactId));
    }

    public function getContactFileFieldsPromise($contactId)
    {
        return Promise\Create::promiseFor($this->getContactFileFields($contactId));
    }
}
