<?php

namespace App\Services;

use Illuminate\Support\Str;

class GenealogyTreeServiceMatcher
{
    /**
     * Main onboarding processes whose initial work requires the client tree.
     * Names are normalized before comparison so historical accents and aliases
     * do not affect the rule.
     *
     * @var array<int, string>
     */
    private const TREE_PROCESS_SERVICES = [
        'espanola lmd',
        'espanola lmd hermano',
        'ley de memoria democratica',
        'ley de memoria democratica hermano',
        'formalizacion anticipada ley de memoria democratica',
        'espanola sefardi',
        'espanola sefardi hermano',
        'nacionalidad espanola por origen sefardi',
        'nacionalidad espanola por origen sefardi hermano',
        'espanola sefardi subsanacion',
        'espanola sefardi subsanacion hermano',
        'espanola carta de naturaleza',
        'espanola carta de naturaleza hermano',
        'nacionalidad espanola por carta de naturaleza',
        'nacionalidad espanola por carta de naturaleza hermano',
        'portuguesa sefardi',
        'portuguesa sefardi hermano',
        'nacionalidad portuguesa por origen sefardi',
        'nacionalidad portuguesa por origen sefardi hermano',
        'portuguesa sefardi subsanacion',
        'portuguesa sefardi subsanacion hermano',
        'formalizacion anticipada portuguesa sefardi',
        'italiana',
        'italiana hermano',
        'nacionalidad italiana',
        'nacionalidad italiana hermano',
        'diagnostico express para plan de accion de la nacionalidad italiana',
        'nacionalidad espanola para familiares',
        'nacionalidad portuguesa para familiares',
    ];

    /**
     * The catalogue also contains individually purchasable genealogy work.
     * These terms identify it without accidentally changing legal, document,
     * or business-linkage services.
     *
     * @var array<int, string>
     */
    private const TREE_PROCESS_KEYWORDS = [
        'genealog',
        'arbol',
        'linaje',
    ];

    public static function requiresGetInfo(?string $serviceCode, ?string $serviceName = null): bool
    {
        foreach ([$serviceCode, $serviceName] as $value) {
            $normalized = self::normalize($value);

            if ($normalized === '') {
                continue;
            }

            if (in_array($normalized, self::TREE_PROCESS_SERVICES, true)
                || Str::contains($normalized, self::TREE_PROCESS_KEYWORDS)) {
                return true;
            }
        }

        return false;
    }

    private static function normalize(?string $value): string
    {
        $normalized = Str::lower(trim((string) Str::ascii((string) $value)));

        return preg_replace('/[\s-]+/u', ' ', $normalized) ?? $normalized;
    }
}
