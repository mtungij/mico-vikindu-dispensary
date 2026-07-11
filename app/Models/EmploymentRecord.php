<?php

namespace App\Models;

use App\Enums\EmploymentCategory;
use App\Enums\EmploymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'staff_profile_id', 'facility_id', 'job_title_id', 'primary_department_id',
    'employment_category', 'employment_status', 'employment_start_date',
    'probation_end_date', 'contract_start_date', 'contract_end_date',
    'termination_date', 'termination_reason', 'payroll_number', 'supervisor_user_id',
    'work_location', 'notes', 'created_by', 'updated_by',
])]
class EmploymentRecord extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'employment_category' => EmploymentCategory::class,
            'employment_status' => EmploymentStatus::class,
            'employment_start_date' => 'date',
            'probation_end_date' => 'date',
            'contract_start_date' => 'date',
            'contract_end_date' => 'date',
            'termination_date' => 'date',
        ];
    }

    public function staffProfile(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class);
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function jobTitle(): BelongsTo
    {
        return $this->belongsTo(JobTitle::class);
    }

    public function primaryDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'primary_department_id');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_user_id');
    }
}
