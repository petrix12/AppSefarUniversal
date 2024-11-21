<?php

namespace App\Services;

use HubSpot\Factory;
use HubSpot\Client\Crm\Contacts\ApiException;

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
        } catch (ApiException $e) {
            throw new \Exception('Error al buscar el contacto en HubSpot: ' . $e->getMessage());
        }
    }

    /**
     * Obtener un contacto por ID.
     */
    public function getContactById($id)
    {
        try {
            // Obtener todas las propiedades disponibles para contactos
            $allPropertiesResponse = $this->hubspot->crm()->properties()->coreApi()->getAll('contacts');
            $allProperties = $allPropertiesResponse->getResults();

            // Extraer los nombres de las propiedades
            $propertyNames = array_map(function($property) {
                return $property->getName();
            }, $allProperties);

            // Utilizar la API de Batch para obtener el contacto con todas las propiedades
            $batchReadInputSimplePublicObjectId = new \HubSpot\Client\Crm\Contacts\Model\BatchReadInputSimplePublicObjectId([
                'properties' => $propertyNames,
                'inputs' => [
                    ['id' => $id],
                ],
            ]);

            $batchResponse = $this->hubspot->crm()->contacts()->batchApi()->read($batchReadInputSimplePublicObjectId);

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


}
