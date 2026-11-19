<?php

namespace App\Services;

use Illuminate\Support\Str;

/** Nationality phase fields on a translation deal are not translation charges. */
class CosDealPaymentScope
{
    private const NATIONALITY_PHASES = [0, 1, 2, 3, 98, 99];

    public static function allowsDealPhase(array $deal, int $phase): bool
    {
        // Servicio Solicitado is authoritative. The old IT field can still
        // say translation on a correctly classified nationality deal.
        $service = self::firstValue($deal, ['servicio_solicitado2', 'servicio_solicitado']);

        return self::allows($service, self::firstValue($deal, ['dealname', 'titulo']), $phase);
    }

    public static function allowsProjectPhase(array $project, int $phase): bool
    {
        return self::allows(self::projectService($project), self::firstValue($project, ['title', 'project_title']), $phase);
    }

    public static function projectService(array $project): string
    {
        $service = self::firstValue($project, ['service']);
        foreach ($project['custom_fields'] ?? [] as $field) {
            if (($field['definition']['id'] ?? $field['id'] ?? '') === DeterministicTeamleaderDealMatcher::SERVICE_FIELD) {
                $service = is_scalar($field['value'] ?? null) ? trim((string) $field['value']) : $service;
                break;
            }
        }

        return $service;
    }

    public static function allowsEnrichmentField(array $deal, array $project, string $field): bool
    {
        $phase = match (true) {
            preg_match('/^(?:fase_|monto_fase_)([0-3])_/', $field, $match) === 1 => (int) $match[1],
            str_starts_with($field, 'carta_nat_') => 98,
            str_starts_with($field, 'cil___fcje_') => 99,
            default => null,
        };

        return $phase === null
            || (self::allowsDealPhase($deal, $phase) && self::allowsProjectPhase($project, $phase));
    }

    private static function allows(string $service, string $title, int $phase): bool
    {
        if (! in_array($phase, self::NATIONALITY_PHASES, true)) {
            return true;
        }

        $service = Str::ascii(mb_strtolower($service));
        if ($service !== '') {
            return ! preg_match('/^traducci(?:on|ones)\b/', $service);
        }

        // Use a named service at the start or after a title separator only
        // when there is no explicit service; a mention in prose is not enough.
        return ! preg_match('/(?:^|[-:(])\s*traducci(?:on|ones)\b/', Str::ascii(mb_strtolower($title)));
    }

    private static function firstValue(array $record, array $keys): string
    {
        foreach ($keys as $key) {
            if (is_scalar($record[$key] ?? null) && trim((string) $record[$key]) !== '') {
                return trim((string) $record[$key]);
            }
        }

        return '';
    }
}
