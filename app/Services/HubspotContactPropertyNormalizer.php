<?php

namespace App\Services;

class HubspotContactPropertyNormalizer
{
    private const BOOLEAN_PROPERTIES = [
        'conyuge_interesado_en_proceso',
    ];

    public static function normalize(array $properties): array
    {
        foreach (self::BOOLEAN_PROPERTIES as $property) {
            if (! array_key_exists($property, $properties)) {
                continue;
            }

            $properties[$property] = self::booleanValue($properties[$property]);
        }

        return $properties;
    }

    private static function booleanValue(mixed $value): string
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
    }
}
