<?php
namespace App\Casts;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
class UtcDateTime implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes): ?CarbonImmutable
    {
        return $value === null ? null : CarbonImmutable::parse($value, 'UTC');
    }
    public function set($model, string $key, $value, array $attributes): ?string
    {
        return $value === null ? null : ($value instanceof \DateTimeInterface ? CarbonImmutable::instance($value)
            : CarbonImmutable::parse($value, 'UTC'))->utc()->format('Y-m-d H:i:s');
    }
}
