<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use HubSpot\Factory;
use HubSpot\Client\Crm\Properties\ApiException as PropertiesApiException;

class GenerateMigrationFromHubSpot extends Command
{
    /**
     * El nombre y la firma del comando.
     *
     * @var string
     */
    protected $signature = 'hubspot:generate-migration
                            {fields : Lista de campos separados por comas}';

    /**
     * La descripción del comando.
     *
     * @var string
     */
    protected $description = 'Genera una migración basada en los tipos de campos obtenidos desde HubSpot';

    /**
     * Cliente de HubSpot.
     *
     * @var \HubSpot\Client
     */
    private $hubspot;

    /**
     * Constructor del comando.
     */
    public function __construct()
    {
        parent::__construct();

        $this->hubspot = Factory::createWithAccessToken(env('HUBSPOT_KEY'));

    }

    /**
     * Ejecuta el comando.
     *
     * @return int
     */
    public function handle()
    {
        // Obtener los campos del argumento
        $fieldsText = $this->argument('fields');
        $fields = array_map('trim', explode(',', $fieldsText)); // Convertir en arreglo y limpiar espacios

        if (empty($fields)) {
            $this->error('Debes proporcionar al menos un campo.');
            return 1;
        }

        $this->info("Obteniendo tipos desde HubSpot para los siguientes campos:\n" . implode(', ', $fields));

        try {
            // Obtener los tipos de los campos desde HubSpot
            $fieldTypes = $this->getFieldTypesFromHubSpot($fields);

            // Generar la migración
            $migration = $this->generateMigration($fieldTypes);

            // Mostrar el código generado
            $this->info("\nCódigo de la migración generado:\n\n$migration");

            return 0;
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Obtiene los tipos de los campos desde HubSpot.
     */
    private function getFieldTypesFromHubSpot($fields)
    {
        $fieldTypes = [];

        try {
            // Obtener todas las propiedades de contactos desde HubSpot
            $properties = $this->hubspot->crm()->properties()->coreApi()->getAll('contacts')->getResults();

            foreach ($properties as $property) {
                $fieldName = $property->getName();

                if (in_array($fieldName, $fields)) {
                    $fieldTypes[$fieldName] = $property->getType();
                }
            }

            return $fieldTypes;
        } catch (PropertiesApiException $e) {
            throw new \Exception('Error al obtener propiedades desde HubSpot: ' . $e->getMessage());
        }
    }

    /**
     * Genera la migración en formato Laravel.
     */
    private function generateMigration($fieldTypes)
    {
        $migration = "Schema::table('users', function (Blueprint \$table) {\n";

        foreach ($fieldTypes as $field => $type) {
            $dbType = $this->mapType($type);

            $migration .= "    \$table->$dbType('$field')->nullable();\n";
        }

        $migration .= "});";

        return $migration;
    }

    /**
     * Mapea el tipo de HubSpot al tipo de columna de Laravel.
     */
    private function mapType($hubspotType)
    {
        // Mapa de tipos de HubSpot a tipos de Laravel/MySQL
        $typeMap = [
            'string' => 'string',
            'number' => 'integer',
            'date' => 'date',
            'datetime' => 'dateTime',
            'boolean' => 'boolean',
            'enumeration' => 'string', // Opciones enumeradas
        ];

        return $typeMap[$hubspotType] ?? 'string'; // Tipo por defecto
    }
}
