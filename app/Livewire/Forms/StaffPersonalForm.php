<?php

namespace App\Livewire\Forms;

use App\Enums\Gender;
use App\Enums\MaritalStatus;
use App\Models\StaffProfile;
use Illuminate\Validation\Rule;
use Livewire\Form;

class StaffPersonalForm extends Form
{
    public ?int $id = null;
    public ?string $employee_number = null;
    public string $first_name = '';
    public ?string $middle_name = null;
    public string $last_name = '';
    public ?string $gender = null;
    public ?string $date_of_birth = null;
    public ?string $marital_status = null;
    public string $nationality = 'Tanzanian';
    public ?string $nida_number = null;
    public ?string $passport_number = null;
    public string $primary_phone = '';
    public ?string $secondary_phone = null;
    public ?string $personal_email = null;
    public ?string $physical_address = null;
    public ?string $postal_address = null;
    public ?string $region = null;
    public ?string $district = null;
    public ?string $ward = null;
    public ?string $street_or_village = null;
    public ?string $biography = null;
    public ?string $emergency_notes = null;

    public function fillFromModel(StaffProfile $profile): void
    {
        $this->id = $profile->id;
        foreach (array_keys($this->rules()) as $key) {
            $property = str($key)->after('personal.')->toString();
            if (property_exists($this, $property)) {
                $this->{$property} = $profile->{$property}?->value ?? $profile->{$property};
            }
        }
    }

    public function rules(): array
    {
        $facilityId = currentFacility()?->id;

        return [
            'employee_number' => [
                'nullable',
                'alpha_dash',
                'max:40',
                Rule::unique('staff_profiles', 'employee_number')->where('facility_id', $facilityId)->ignore($this->id),
            ],
            'first_name' => ['required', 'string', 'max:50'],
            'middle_name' => ['nullable', 'string', 'max:50'],
            'last_name' => ['required', 'string', 'max:50'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'marital_status' => ['nullable', Rule::enum(MaritalStatus::class)],
            'nationality' => ['required', 'string', 'max:80'],
            'nida_number' => ['nullable', 'string', 'max:40', Rule::unique('staff_profiles', 'nida_number')->where('facility_id', $facilityId)->ignore($this->id)],
            'passport_number' => ['nullable', 'string', 'max:40', Rule::unique('staff_profiles', 'passport_number')->where('facility_id', $facilityId)->ignore($this->id)],
            'primary_phone' => ['required', 'string', 'max:30'],
            'secondary_phone' => ['nullable', 'string', 'max:30', 'different:primary_phone'],
            'personal_email' => ['nullable', 'email:rfc', 'max:150'],
            'physical_address' => ['nullable', 'string', 'max:1000'],
            'postal_address' => ['nullable', 'string', 'max:1000'],
            'region' => ['nullable', 'string', 'max:80'],
            'district' => ['nullable', 'string', 'max:80'],
            'ward' => ['nullable', 'string', 'max:80'],
            'street_or_village' => ['nullable', 'string', 'max:100'],
            'biography' => ['nullable', 'string', 'max:2000'],
            'emergency_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function data(): array
    {
        $data = $this->validate();
        $data['employee_number'] = filled($data['employee_number'] ?? null) ? str($data['employee_number'])->upper()->toString() : null;

        return $data;
    }

    public function resetForm(): void
    {
        $this->reset();
        $this->nationality = 'Tanzanian';
    }
}
