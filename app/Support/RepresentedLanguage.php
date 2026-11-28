<?php

namespace App\Support;

class RepresentedLanguage
{
    /** Display legacy role and relationship labels without changing stored values. */
    public static function label(?string $value): string
    {
        return preg_replace_callback('/\bclientes?\b/iu', function ($match) {
            $label = str_ends_with(strtolower($match[0]), 's') ? 'representados' : 'representado';
            if (strtoupper($match[0]) === $match[0]) return strtoupper($label);
            return ctype_upper($match[0][0]) ? ucfirst($label) : $label;
        }, $value ?? '');
    }
}
