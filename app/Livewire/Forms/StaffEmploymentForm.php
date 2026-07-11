<?php

namespace App\Livewire\Forms;

use App\Enums\EmploymentCategory;
use App\Enums\EmploymentStatus;
use Illuminate\Validation\Rule;
use Livewire\Form;

class StaffEmploymentForm extends Form
{
    public ?int $job_title_id = null;
    public ?int $primary_department_id = null;
    public ?string $employment_category = null;
    public string $employment_status = 'active';
    public ?string $employment_start_date = null;
    public ?string $probation_end_date = null;
    public ?string $contract_start_date = null;
    public ?string $contract_end_date = null;
    public ?string $termination_date = null;
    public ?string $termination_reason = null;
    public ?string $payroll_number = null;
    public ?int $supervisor_user_id = null;
    public ?string $work_location = null;
    public ?string $notes = null;

    public function rules(): array
    {
        $facilityId = currentFacility()?->id;

        return [
            'job_title_id' => ['nullable', Rule::exists('job_titles', 'id')->where('facility_id', $facilityId)],
            'primary_department_id' => ['nullable', Rule::exists('departments', 'id')->where('facility_id', $facilityId)],
            'employment_category' => ['nullable', Rule::enum(EmploymentCategory::class)],
            'employment_status' => ['required', Rule::enum(EmploymentStatus::class)],
            'employment_start_date' => ['nullable', 'date'],
            'probation_end_date' => ['nullable', 'date', 'after_or_equal:employment_start_date'],
            'contract_start_date' => ['nullable', 'date'],
            'contract_end_date' => ['nullable', 'date', 'after_or_equal:contract_start_date'],
            'termination_date' => ['nullable', 'required_if:employment_status,terminated', 'date'],
            'termination_reason' => ['nullable', 'required_if:employment_status,terminated', 'string', 'max:1000'],
            'payroll_number' => ['nullable', 'string', 'max:80'],
            'supervisor_user_id' => ['nullable', Rule::exists('users', 'id')],
            'work_location' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function data(): array
    {
        return $this->validate();
    }
}
