<?php

namespace App\Models;

use App\Enums\UserStatus;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'phone', 'password', 'status', 'employee_number', 'primary_department_id', 'job_title_id', 'is_super_admin', 'last_login_at', 'last_login_ip', 'password_changed_at', 'must_change_password', 'avatar_path'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
            'is_super_admin' => 'boolean',
            'must_change_password' => 'boolean',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function isSuspended(): bool
    {
        return $this->status === UserStatus::Suspended;
    }

    public function canLogin(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function fullStaffName(): string
    {
        return $this->staffProfile?->fullName() ?? $this->name;
    }

    public function belongsToCurrentFacility(): bool
    {
        return $this->is_super_admin || $this->staffProfile?->facility_id === currentFacility()?->id;
    }

    public function staffProfile(): HasOne
    {
        return $this->hasOne(StaffProfile::class);
    }

    public function employmentRecord(): HasOneThrough
    {
        return $this->hasOneThrough(EmploymentRecord::class, StaffProfile::class);
    }

    public function createdFacilities(): HasMany
    {
        return $this->hasMany(Facility::class, 'created_by');
    }

    public function updatedFacilities(): HasMany
    {
        return $this->hasMany(Facility::class, 'updated_by');
    }

    public function uploadedFacilityDocuments(): HasMany
    {
        return $this->hasMany(FacilityDocument::class, 'uploaded_by');
    }

    public function loginHistories(): HasMany
    {
        return $this->hasMany(UserLoginHistory::class);
    }

    public function supervisorOf(): HasMany
    {
        return $this->hasMany(EmploymentRecord::class, 'supervisor_user_id');
    }

    public function primaryDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'primary_department_id');
    }

    public function jobTitle(): BelongsTo
    {
        return $this->belongsTo(JobTitle::class);
    }

    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class)
            ->withPivot(['is_primary', 'can_receive_queue', 'can_manage_department', 'assigned_by', 'assigned_at'])
            ->withTimestamps();
    }
}
