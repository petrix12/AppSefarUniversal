<?php

namespace App\Services;

class CosPresentation
{
    public static function dateInput(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            $value = $value->format('Y-m-d');
        }
        if (! is_string($value)) {
            return '';
        }

        $value = trim($value);
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[T ]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:?\d{2})?)?$/D', $value, $parts)) {
            [, $year, $month, $day] = $parts;
        } elseif (preg_match('~^(\d{1,2})/(\d{1,2})/(\d{4})$~D', $value, $parts)) {
            [, $day, $month, $year] = $parts;
        } else {
            return '';
        }

        return checkdate((int) $month, (int) $day, (int) $year)
            ? sprintf('%04d-%02d-%02d', $year, $month, $day)
            : '';
    }

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
