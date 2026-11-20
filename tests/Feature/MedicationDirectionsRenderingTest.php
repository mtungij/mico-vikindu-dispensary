<?php

namespace Tests\Feature;

use App\Models\PrescriptionItem;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MedicationDirectionsRenderingTest extends TestCase
{
    #[DataProvider('durations')]
    public function test_shared_directions_preserve_duration_and_missing_value_fallbacks(?string $unit, string $expected): void
    {
        $item = new PrescriptionItem(['duration_unit' => $unit]);

        $this->blade('<x-medication-directions :item="$item" />', ['item' => $item])
            ->assertSee($expected)
            ->assertSee('Not specified')
            ->assertSee('grid-cols-1')
            ->assertSee('sm:grid-cols-2')
            ->assertSee('[overflow-wrap:anywhere]', false)
            ->assertSee('whitespace-pre-wrap')
            ->assertSee('dark:text-slate-200');
    }

    public static function durations(): array
    {
        return [
            'until finished' => ['until_finished', 'Until finished'],
            'single dose' => ['single_dose', 'Single dose'],
            'missing duration' => [null, 'Not specified'],
            'incomplete duration' => ['days', 'Not specified'],
        ];
    }
}
