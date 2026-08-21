<?php

namespace App\Livewire\Forms;

use App\Support\MedicationDirections;
use Illuminate\Validation\Rule;
use Livewire\Form;

class PrescriptionItemForm extends Form
{
    public ?int $medicine_id = null;

    public string $medication_name = '';

    public ?string $generic_name = null;

    public ?string $strength = null;

    public ?string $dosage_form = null;

    public string $dose = '';

    public string $dose_choice = '';

    public string $custom_dose = '';

    public ?string $route = null;

    public string $route_choice = '';

    public string $custom_route = '';

    public string $frequency = '';

    public string $frequency_choice = '';

    public string $custom_frequency = '';

    public ?string $duration_value = null;

    public string $duration_unit = 'days';

    public ?string $quantity = null;

    public bool $quantity_manually_adjusted = false;

    public ?string $calculation_summary = null;

    public ?string $instructions = null;

    public ?string $indication = null;

    public bool $substitution_allowed = true;

    public function rules(): array
    {
        return [
            'medicine_id' => ['required', 'integer'],
            'medication_name' => ['nullable', 'string', 'max:255'],
            'dose' => ['required', 'string', 'max:100', function ($attribute, $value, $fail): void {
                if (! preg_match('/[\pL]/u', trim((string) $value)) || preg_match('/^\s*\d+(?:\.\d+)?\s*$/', (string) $value)) {
                    $fail('Weka dose yenye kiasi na unit, mfano 1 capsule au 5 mL.');
                }
            }],
            'frequency' => ['required', 'string', 'max:100', function ($attribute, $value, $fail): void {
                $customIsMeaningful = $this->frequency_choice === 'custom' && mb_strlen(trim((string) $value)) >= 3 && preg_match('/[\pL]/u', trim((string) $value));
                if (! MedicationDirections::normalizeFrequency($value) && ! $customIsMeaningful) {
                    $fail('Chagua frequency sahihi ya dawa.');
                }
            }],
            'duration_value' => [Rule::requiredIf(! in_array($this->duration_unit, ['until_finished', 'single_dose'], true)), 'nullable', 'integer', 'min:1'],
            'duration_unit' => ['required', Rule::in(MedicationDirections::DURATION_UNITS)],
            'route' => ['required', 'string', 'max:100', function ($attribute, $value, $fail): void {
                $customIsMeaningful = $this->route_choice === 'custom' && mb_strlen(trim((string) $value)) >= 3 && preg_match('/[\pL]/u', trim((string) $value));
                if (! MedicationDirections::normalizeRoute($value) && ! $customIsMeaningful) {
                    $fail('Chagua route sahihi ya dawa.');
                }
            }],
            'quantity' => ['required', 'numeric', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'quantity.required' => 'Weka kiasi cha dawa. Kiasi lazima kiwe angalau 1.',
            'quantity.numeric' => 'Weka kiasi cha dawa. Kiasi lazima kiwe angalau 1.',
            'quantity.min' => 'Weka kiasi cha dawa. Kiasi lazima kiwe angalau 1.',
            'frequency.required' => 'Chagua frequency sahihi ya dawa.',
            'duration_value.required' => 'Weka muda wa matumizi ya dawa.',
            'duration_value.integer' => 'Muda wa matumizi lazima uwe namba kamili.',
            'duration_value.min' => 'Muda wa matumizi lazima uwe angalau 1.',
            'duration_unit.in' => 'Chagua duration unit sahihi.',
            'route.required' => 'Chagua route ya dawa.',
        ];
    }

    public function validationAttributes(): array
    {
        return ['medication_name' => 'jina la dawa'];
    }

    public function normalize(): array
    {
        return [
            'medicine_id' => $this->medicine_id,
            'medication_name' => $this->medication_name,
            'generic_name' => $this->generic_name,
            'strength' => $this->strength,
            'dosage_form' => $this->dosage_form,
            'dose' => trim($this->dose),
            'route' => trim((string) $this->route),
            'frequency' => trim($this->frequency),
            'duration_value' => in_array($this->duration_unit, ['until_finished', 'single_dose'], true) ? 1 : (filled($this->duration_value) ? (int) $this->duration_value : null),
            'duration_unit' => $this->duration_unit,
            'quantity' => filled($this->quantity) ? (float) $this->quantity : null,
            'instructions' => $this->instructions,
            'indication' => $this->indication,
            'substitution_allowed' => $this->substitution_allowed,
            'frequency_is_custom' => $this->frequency_choice === 'custom',
            'route_is_custom' => $this->route_choice === 'custom',
        ];
    }

    public function fillFromModel($model): void
    {
        $this->fill($model->only(['medicine_id', 'medication_name', 'generic_name', 'strength', 'dosage_form', 'dose', 'route', 'frequency', 'duration_value', 'duration_unit', 'quantity', 'instructions', 'indication', 'substitution_allowed']));
        $this->dose_choice = $this->dose;
        $this->frequency_choice = MedicationDirections::normalizeFrequency($this->frequency) ?? 'custom';
        $this->custom_frequency = $this->frequency_choice === 'custom' ? $this->frequency : '';
        $this->route_choice = MedicationDirections::normalizeRoute($this->route) ?? 'custom';
        $this->custom_route = $this->route_choice === 'custom' ? (string) $this->route : '';
        $this->quantity_manually_adjusted = MedicationDirections::calculateQuantity($this->dose, $this->frequency, $this->duration_value, $this->duration_unit) !== (float) $this->quantity;
    }

    public function resetForm(): void
    {
        $this->reset();
        $this->duration_unit = 'days';
        $this->substitution_allowed = true;
    }
}
