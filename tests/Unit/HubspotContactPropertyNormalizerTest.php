<?php

namespace Tests\Unit;

use App\Services\HubspotContactPropertyNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class HubspotContactPropertyNormalizerTest extends TestCase
{
    public static function booleanValues(): array
    {
        return [
            'integer enabled' => [1, 'true'],
            'string integer enabled' => ['1', 'true'],
            'boolean enabled' => [true, 'true'],
            'string enabled' => ['true', 'true'],
            'integer disabled' => [0, 'false'],
            'string integer disabled' => ['0', 'false'],
            'boolean disabled' => [false, 'false'],
            'string disabled' => ['false', 'false'],
            'null disabled' => [null, 'false'],
        ];
    }

    #[DataProvider('booleanValues')]
    public function test_it_normalizes_the_spouse_interest_property(mixed $input, string $expected): void
    {
        $properties = HubspotContactPropertyNormalizer::normalize([
            'email' => 'cliente@example.com',
            'conyuge_interesado_en_proceso' => $input,
        ]);

        $this->assertSame($expected, $properties['conyuge_interesado_en_proceso']);
        $this->assertSame('cliente@example.com', $properties['email']);
    }

    public function test_it_does_not_add_the_property_when_it_was_not_sent(): void
    {
        $this->assertSame(
            ['firstname' => 'Ada'],
            HubspotContactPropertyNormalizer::normalize(['firstname' => 'Ada'])
        );
    }
}
