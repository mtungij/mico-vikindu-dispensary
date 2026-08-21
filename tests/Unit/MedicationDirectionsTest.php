<?php

namespace Tests\Unit;

use App\Support\MedicationDirections;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MedicationDirectionsTest extends TestCase
{
    #[DataProvider('deterministicDirections')]
    public function test_deterministic_directions_calculate_safe_quantities(string $dose, string $frequency, int $duration, string $unit, float $expected): void
    {
        $this->assertSame($expected, MedicationDirections::calculateQuantity($dose, $frequency, $duration, $unit));
    }

    public static function deterministicDirections(): array
    {
        return [
            'once daily seven days' => ['1 tablet', 'Once daily', 7, 'days', 7.0],
            'capsule tds seven days' => ['1 capsule', 'Three times daily', 7, 'days', 21.0],
            'two tablets bd five days' => ['2 tablets', 'Twice daily', 5, 'days', 20.0],
            'every eight hours' => ['1 capsule', 'Every 8 hours', 7, 'days', 21.0],
            'every six hours' => ['1 tablet', 'Every 6 hours', 5, 'days', 20.0],
            'every twelve hours' => ['1 capsule', 'Every 12 hours', 7, 'days', 14.0],
            'every four hours for twelve hours' => ['1 capsule', 'Every 4 hours', 12, 'hours', 3.0],
            'legacy tds' => ['1 capsule', 'TDS', 7, 'days', 21.0],
            'half tablet' => ['1/2 tablet', 'BD', 4, 'days', 4.0],
            'stat once' => ['2 tablets', 'Stat / once', 1, 'single_dose', 2.0],
        ];
    }

    public function test_non_deterministic_directions_do_not_guess_quantity(): void
    {
        $this->assertNull(MedicationDirections::calculateQuantity('1 capsule', 'As needed / PRN', 7, 'days'));
        $this->assertNull(MedicationDirections::calculateQuantity('1 capsule', 'When fever rises', 7, 'days'));
        $this->assertNull(MedicationDirections::calculateQuantity('500 mg', 'Three times daily', 7, 'days'));
        $this->assertNull(MedicationDirections::calculateQuantity('3', 'Three times daily', 7, 'days'));
        $this->assertNull(MedicationDirections::calculateQuantity('1 capsule', 'Three times daily', 1, 'until_finished'));
    }

    public function test_historical_frequency_and_route_aliases_are_normalized_for_display(): void
    {
        $this->assertSame('Once daily', MedicationDirections::displayFrequency('OD'));
        $this->assertSame('Twice daily', MedicationDirections::displayFrequency('BD'));
        $this->assertSame('Three times daily', MedicationDirections::displayFrequency('TDS'));
        $this->assertSame('Four times daily', MedicationDirections::displayFrequency('QID'));
        $this->assertSame('Oral', MedicationDirections::displayRoute('PO'));
        $this->assertSame('Intravenous', MedicationDirections::displayRoute('IV'));
    }
}
