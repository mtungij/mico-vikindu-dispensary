<?php

namespace App\Livewire\Forms;

use App\Enums\Gender;
use App\Enums\MaritalStatus;
use Illuminate\Validation\Rule;
use Livewire\Form;

class PatientPersonalForm extends Form
{
    public ?string $first_name = null; public ?string $middle_name = null; public ?string $last_name = null; public ?string $gender = null;
    public ?string $date_of_birth = null; public ?int $age_years = null; public ?int $age_months = null; public bool $date_of_birth_is_estimated = false;
    public ?string $marital_status = null; public string $nationality = 'Tanzanian'; public ?string $nida_number = null; public ?string $passport_number = null;
    public ?string $primary_phone = null; public ?string $secondary_phone = null; public ?string $email = null; public ?string $region = null; public ?string $district = null; public ?string $ward = null; public ?string $street_or_village = null; public ?string $physical_address = null; public ?string $occupation = null; public string $blood_group = 'unknown'; public ?string $known_allergies = null; public ?string $chronic_conditions = null; public string $patient_status = 'active'; public bool $profile_incomplete = false;

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:60'], 'last_name' => ['required', 'string', 'max:60'], 'middle_name' => ['nullable', 'string', 'max:60'],
            'gender' => ['required', Rule::enum(Gender::class)], 'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'], 'age_years' => ['nullable', 'integer', 'min:0', 'max:130'], 'age_months' => ['nullable', 'integer', 'min:0', 'max:11'], 'date_of_birth_is_estimated' => ['boolean'],
            'marital_status' => ['nullable', Rule::enum(MaritalStatus::class)], 'nationality' => ['required', 'string', 'max:80'], 'nida_number' => ['nullable', 'string', 'max:40'], 'passport_number' => ['nullable', 'string', 'max:40'], 'primary_phone' => ['nullable', 'string', 'max:30'], 'secondary_phone' => ['nullable', 'string', 'max:30'], 'email' => ['nullable', 'email'], 'region' => ['nullable', 'string', 'max:80'], 'district' => ['nullable', 'string', 'max:80'], 'ward' => ['nullable', 'string', 'max:80'], 'street_or_village' => ['nullable', 'string', 'max:100'], 'physical_address' => ['nullable', 'string', 'max:1000'], 'occupation' => ['nullable', 'string', 'max:100'], 'blood_group' => ['nullable', 'string', 'max:10'], 'known_allergies' => ['nullable', 'string'], 'chronic_conditions' => ['nullable', 'string'], 'patient_status' => ['required', 'string'], 'profile_incomplete' => ['boolean'],
        ];
    }
    public function data(): array { return $this->validate(); }
    public function resetForm(): void { $this->reset(); $this->nationality = 'Tanzanian'; $this->blood_group = 'unknown'; $this->patient_status = 'active'; }
}
