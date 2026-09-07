<?php

namespace App\Services;

class CosPresentation
{
    public static function portugueseLabels(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map([self::class, 'portugueseLabels'], $value);
        }
        if (! is_string($value)) {
            return $value;
        }

        return preg_replace(
            ['/\bel\s+recurso\s+de\s+urgencia\b/iu', '/\bun(?:a)?\s+recurso\s+de\s+urgencia\b/iu', '/\brecurso\s+de\s+urgencia\b/iu'],
            ['la Auditoría de Expedientes', 'una Auditoría de Expedientes', 'Auditoría de Expedientes'],
            $value
        );
    }

    public static function statuses(array $statuses): array
    {
        return array_map(function ($status) {
            return is_array($status) && str_starts_with(mb_strtolower(trim($status['servicio'] ?? '')), 'portuguesa sefardi')
                ? self::portugueseLabels($status)
                : $status;
        }, $statuses);
    }
}
