<?php

namespace App\Livewire\Forms;

use App\Enums\EducationLevel;
use App\Enums\EmploymentCategory;
use App\Models\JobTitle;
use Illuminate\Validation\Rule;
use Livewire\Form;

class JobTitleForm extends Form
{
    public ?int $id = null;
    public ?int $department_id = null;
    public string $name = '';
    public string $code = '';
    public ?string $description = null;
    public ?string $employment_category = null;
    public bool $requires_professional_license = false;
    public ?string $license_authority = null;
    public ?string $minimum_education_level = null;
    public bool $is_clinical = false;
    public bool $is_active = true;
    public int $sort_order = 0;

    public function setJobTitle(JobTitle $jobTitle): void
    {
        $this->id = $jobTitle->id;
        $this->department_id = $jobTitle->department_id;
        $this->name = $jobTitle->name;
        $this->code = $jobTitle->code;
        $this->description = $jobTitle->description;
        $this->employment_category = $jobTitle->employment_category?->value;
        $this->requires_professional_license = $jobTitle->requires_professional_license;
        $this->license_authority = $jobTitle->license_authority;
        $this->minimum_education_level = $jobTitle->minimum_education_level?->value;
        $this->is_clinical = $jobTitle->is_clinical;
        $this->is_active = $jobTitle->is_active;
        $this->sort_order = $jobTitle->sort_order;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $facilityId = currentFacility()?->id;

        return [
            'department_id' => [
                'nullable',
                Rule::exists('departments', 'id')->where('facility_id', $facilityId),
            ],
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('job_titles', 'name')->where('facility_id', $facilityId)->ignore($this->id),
            ],
            'code' => [
                'required',
                'alpha_dash',
                'max:20',
                Rule::unique('job_titles', 'code')->where('facility_id', $facilityId)->ignore($this->id),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'employment_category' => ['nullable', Rule::enum(EmploymentCategory::class)],
            'requires_professional_license' => ['boolean'],
            'license_authority' => ['nullable', 'required_if:requires_professional_license,true', 'string', 'max:100'],
            'minimum_education_level' => ['nullable', Rule::enum(EducationLevel::class)],
            'is_clinical' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0', 'max:9999'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        $validated = $this->validate();
        $validated['code'] = str($validated['code'])->upper()->toString();

        return $validated;
    }

    public function resetForm(): void
    {
        $this->reset();
        $this->is_active = true;
    }
}
