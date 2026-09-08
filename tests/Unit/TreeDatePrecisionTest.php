<?php

namespace Tests\Unit;

use App\Http\Controllers\AgClienteNewController;
use PHPUnit\Framework\TestCase;

class TreeDatePrecisionTest extends TestCase
{
    /**
     * @dataProvider acceptedDates
     */
    public function test_tree_dates_keep_the_precision_supplied_by_the_source(string $date, array $expected): void
    {
        $controller = new AgClienteNewController();
        $parser = new \ReflectionMethod($controller, 'parseTreeDate');

        $this->assertSame($expected, $parser->invoke($controller, $date));
    }

    public static function acceptedDates(): array
    {
        return [
            'year only' => ['1885', ['year' => 1885, 'month' => null, 'day' => null]],
            'month and year' => ['04/1885', ['year' => 1885, 'month' => 4, 'day' => null]],
            'ISO month and year' => ['1885-04', ['year' => 1885, 'month' => 4, 'day' => null]],
            'full Latin date' => ['29/02/2000', ['year' => 2000, 'month' => 2, 'day' => 29]],
            'full ISO date' => ['2000-02-29', ['year' => 2000, 'month' => 2, 'day' => 29]],
        ];
    }

    /**
     * @dataProvider invalidDates
     */
    public function test_tree_dates_reject_impossible_or_ambiguous_values(string $date): void
    {
        $controller = new AgClienteNewController();
        $parser = new \ReflectionMethod($controller, 'parseTreeDate');

        $this->assertNull($parser->invoke($controller, $date));
    }

    public static function invalidDates(): array
    {
        return [
            'month without a valid range' => ['13/1885'],
            'impossible day' => ['31/04/1885'],
            'non leap year' => ['29/02/1900'],
            'day without month' => ['31/1885/'],
            'out of range year' => ['3001'],
        ];
    }
}
