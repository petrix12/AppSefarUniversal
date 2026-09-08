<?php

namespace Tests\Feature;

use App\Http\Controllers\AgClienteNewController;
use Illuminate\Http\Request;
use Tests\TestCase;

class TreeDatePrecisionRequestTest extends TestCase
{
    public function test_tree_request_saves_only_the_known_parts_of_a_partial_date(): void
    {
        $parts = $this->datePartsFrom([
            'FechaNac' => '04/1885',
            'FechaBtzo' => '1885',
            'FechaMatr' => '1885-04-17',
        ]);

        $this->assertSame(1885, $parts['AnhoNac']);
        $this->assertSame(4, $parts['MesNac']);
        $this->assertNull($parts['DiaNac']);
        $this->assertSame(1885, $parts['AnhoBtzo']);
        $this->assertNull($parts['MesBtzo']);
        $this->assertNull($parts['DiaBtzo']);
        $this->assertSame(17, $parts['DiaMatr']);
    }

    public function test_blank_current_date_field_clears_legacy_parts(): void
    {
        $parts = $this->datePartsFrom([
            'tree_date_precision_input' => 1,
            'FechaNac' => '',
            'AnhoNac' => 1885,
            'MesNac' => 4,
            'DiaNac' => 17,
        ]);

        $this->assertNull($parts['AnhoNac']);
        $this->assertNull($parts['MesNac']);
        $this->assertNull($parts['DiaNac']);
    }

    public function test_legacy_component_only_request_remains_supported(): void
    {
        $parts = $this->datePartsFrom([
            'AnhoNac' => 1885,
            'MesNac' => 4,
            'DiaNac' => 17,
        ]);

        $this->assertSame(1885, $parts['AnhoNac']);
        $this->assertSame(4, $parts['MesNac']);
        $this->assertSame(17, $parts['DiaNac']);
    }

    private function datePartsFrom(array $input): array
    {
        $controller = app(AgClienteNewController::class);
        $method = new \ReflectionMethod($controller, 'datePartsFromRequest');

        return $method->invoke($controller, Request::create('/arbol', 'POST', $input));
    }
}
