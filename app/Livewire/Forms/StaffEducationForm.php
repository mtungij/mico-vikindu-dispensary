<?php

namespace App\Livewire\Forms;

use App\Enums\EducationLevel;
use Illuminate\Validation\Rule;
use Livewire\Form;

class StaffEducationForm extends Form
{
    public ?string $education_level = null;
    public ?string $course_name = null;
    public ?string $institution_name = null;
    public ?string $country = 'Tanzania';
    public ?int $start_year = null;
    public ?int $graduation_year = null;
    public ?string $certificate_number = null;
    public ?string $grade_or_class = null;
    public ?string $description = null;
    public bool $is_highest_qualification = false;

    public function rules(): array
    {
        return [
            'education_level' => ['required', Rule::enum(EducationLevel::class)],
            'course_name' => ['required', 'string', 'max:150'],
            'institution_name' => ['required', 'string', 'max:150'],
            'country' => ['nullable', 'string', 'max:80'],
            'start_year' => ['nullable', 'integer', 'min:1950', 'max:'.now()->year],
            'graduation_year' => ['nullable', 'integer', 'min:1950', 'max:'.(now()->year + 10), 'gte:start_year'],
            'certificate_number' => ['nullable', 'string', 'max:100'],
            'grade_or_class' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_highest_qualification' => ['boolean'],
        ];
    }

    public function data(): array
    {
        return $this->validate();
    }
}
