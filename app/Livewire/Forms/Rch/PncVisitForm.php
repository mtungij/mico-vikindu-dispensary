<?php

namespace App\Livewire\Forms\Rch;

use Livewire\Form;

class PncVisitForm extends Form
{
    public const VITALS = [
        'temperature' => 'Temperature (°C)',
        'systolic_bp' => 'Systolic BP',
        'diastolic_bp' => 'Diastolic BP',
        'pulse_rate' => 'Pulse',
        'respiratory_rate' => 'Respiratory rate',
        'oxygen_saturation' => 'Oxygen saturation (%)',
        'weight_kg' => 'Weight (kg)',
    ];

    public const NOTES = [
        'delivery_details' => 'Delivery details',
        'maternal_observations' => 'Maternal observations',
        'postpartum_symptoms' => 'Postpartum symptoms',
        'complications' => 'Complications',
        'breastfeeding_assessment' => 'Breastfeeding assessment',
        'breastfeeding_counselling' => 'Breastfeeding counselling',
        'newborn_condition' => 'Newborn condition',
        'newborn_details' => 'Newborn details',
        'clinical_notes' => 'Clinical notes',
        'treatment_plan' => 'Treatment plan',
    ];

    public ?string $visit_date = null;

    public ?string $delivery_date = null;

    public ?string $follow_up_date = null;

    public array $maternal_vitals = [];

    public ?string $delivery_details = null;

    public ?string $maternal_observations = null;

    public ?string $postpartum_symptoms = null;

    public ?string $complications = null;

    public ?string $breastfeeding_assessment = null;

    public ?string $breastfeeding_counselling = null;

    public ?string $newborn_condition = null;

    public ?string $newborn_details = null;

    public ?string $clinical_notes = null;

    public ?string $treatment_plan = null;

    public function rules(): array
    {
        return [
            'visit_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'delivery_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:visit_date'],
            'follow_up_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:visit_date'],
            'maternal_vitals' => ['array:'.implode(',', array_keys(self::VITALS))],
            ...array_fill_keys(array_keys(self::NOTES), ['nullable', 'string', 'max:10000']),
        ];
    }
}
