<?php

namespace App\Livewire\Forms;

use App\Models\FacilitySetting;
use Livewire\Form;

class CashierSessionOpenForm extends Form
{
    public string $shift = 'morning';

    public string $opening_float = '0';

    public ?string $cash_drawer = 'Main Counter';

    public ?string $notes = null;

    public function rules(): array
    {
        $openingFloatRules = ['numeric', 'min:0'];

        if ($this->setting('billing_require_opening_float', false)) {
            array_unshift($openingFloatRules, 'required');
        } else {
            array_unshift($openingFloatRules, 'nullable');
        }

        if (! $this->setting('billing_allow_zero_float', true)) {
            $openingFloatRules[] = 'gt:0';
        }

        return [
            'shift' => ['required', 'in:morning,afternoon,evening,night,custom'],
            'opening_float' => $openingFloatRules,
            'cash_drawer' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function validationAttributes(): array
    {
        return [
            'shift' => 'shift',
            'opening_float' => 'opening float',
            'cash_drawer' => 'cash drawer',
            'notes' => 'notes',
        ];
    }

    public function normalize(): array
    {
        return [
            'shift' => $this->shift,
            'opening_float' => number_format((float) ($this->opening_float ?: 0), 2, '.', ''),
            'cash_drawer' => blank($this->cash_drawer) ? null : trim((string) $this->cash_drawer),
            'notes' => blank($this->notes) ? null : trim((string) $this->notes),
        ];
    }

    public function resetForm(): void
    {
        $this->shift = 'morning';
        $this->opening_float = '0';
        $this->cash_drawer = 'Main Counter';
        $this->notes = null;
    }

    private function setting(string $key, bool $default): bool
    {
        $value = FacilitySetting::query()
            ->where('facility_id', currentFacility()?->id)
            ->where('key', $key)
            ->value('value');

        return $value === null ? $default : filter_var($value, FILTER_VALIDATE_BOOL);
    }
}
