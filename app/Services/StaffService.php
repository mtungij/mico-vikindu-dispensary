<?php

namespace App\Services;

use App\Enums\EmploymentStatus;
use App\Enums\UserStatus;
use App\Models\ActivityLog;
use App\Models\EmploymentRecord;
use App\Models\JobTitle;
use App\Models\StaffEmergencyContact;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class StaffService
{
    public function __construct(
        private readonly PhoneNumberService $phone,
        private readonly StaffNumberService $numbers,
        private readonly StaffDepartmentService $departments,
        private readonly StaffRoleService $roles,
        private readonly LicenseStatusService $licenses,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{staff: StaffProfile, temporary_password: ?string}
     */
    public function createStaff(array $data, User $actor): array
    {
        return DB::transaction(function () use ($data, $actor): array {
            $facilityId = currentFacility()?->id;
            $employeeNumber = $data['personal']['employee_number'] ?? null;
            $employeeNumber = $employeeNumber ? str($employeeNumber)->upper()->toString() : $this->numbers->next($facilityId);

            $temporaryPassword = $data['account']['temporary_password'] ?? null;
            $createActiveAccount = (bool) ($data['account']['create_login_account'] ?? true);
            if (! $createActiveAccount) {
                $temporaryPassword = str()->random(40);
            }

            $user = User::query()->create([
                'name' => $this->fullName($data['personal']),
                'email' => str($data['account']['email'])->lower()->trim()->toString(),
                'phone' => $this->phone->normalize($data['account']['phone'] ?: $data['personal']['primary_phone']),
                'password' => Hash::make($temporaryPassword),
                'status' => $createActiveAccount ? ($data['account']['status'] ?? UserStatus::Active->value) : UserStatus::Inactive,
                'employee_number' => $employeeNumber,
                'must_change_password' => (bool) ($data['account']['must_change_password'] ?? true),
                'password_changed_at' => now(),
            ]);

            $profile = StaffProfile::query()->create([
                ...$data['personal'],
                'user_id' => $user->id,
                'facility_id' => $facilityId,
                'employee_number' => $employeeNumber,
                'primary_phone' => $this->phone->normalize($data['personal']['primary_phone']),
                'secondary_phone' => filled($data['personal']['secondary_phone'] ?? null) ? $this->phone->normalize($data['personal']['secondary_phone']) : null,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $employment = $data['employment'];
            $this->validateEmployment($employment, $facilityId);
            EmploymentRecord::query()->create([
                ...$employment,
                'staff_profile_id' => $profile->id,
                'facility_id' => $facilityId,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $user->update([
                'primary_department_id' => $employment['primary_department_id'] ?? null,
                'job_title_id' => $employment['job_title_id'] ?? null,
            ]);

            $this->departments->syncDepartments(
                $profile->refresh()->load('user', 'employmentRecord'),
                $employment['primary_department_id'] ?? null,
                $data['departments'] ?? [],
                $actor,
            );

            $this->roles->syncRoles($profile, $data['account']['role_ids'] ?? [], $actor);
            if (($data['account']['direct_permissions'] ?? []) !== []) {
                $this->roles->syncDirectPermissions($profile, $data['account']['direct_permissions'], $actor);
            }

            foreach ($data['education'] ?? [] as $education) {
                if (($education['is_highest_qualification'] ?? false) === true) {
                    $profile->educationRecords()->update(['is_highest_qualification' => false]);
                }
                $profile->educationRecords()->create([
                    ...$education,
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                ]);
            }

            foreach ($data['licenses'] ?? [] as $license) {
                $status = $this->licenses->calculate($license['expiry_date'] ?? null);
                $profile->professionalLicenses()->create([
                    ...$license,
                    'status' => $status,
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                ]);
            }

            foreach ($data['emergency_contacts'] ?? [] as $contact) {
                if (($contact['is_primary'] ?? false) === true) {
                    $profile->emergencyContacts()->update(['is_primary' => false]);
                }
                $profile->emergencyContacts()->create([
                    ...$contact,
                    'primary_phone' => $this->phone->normalize($contact['primary_phone']),
                    'secondary_phone' => filled($contact['secondary_phone'] ?? null) ? $this->phone->normalize($contact['secondary_phone']) : null,
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                ]);
            }

            $this->log($actor, 'staff_created', $profile, [], ['employee_number' => $employeeNumber]);

            return [
                'staff' => $profile->refresh()->load(['user.roles', 'employmentRecord.jobTitle', 'employmentRecord.primaryDepartment']),
                'temporary_password' => $createActiveAccount ? $temporaryPassword : null,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updatePersonalDetails(StaffProfile $staffProfile, array $data, User $actor): StaffProfile
    {
        return DB::transaction(function () use ($staffProfile, $data, $actor): StaffProfile {
            $old = $staffProfile->only(array_keys($data));
            $data['primary_phone'] = $this->phone->normalize($data['primary_phone']);
            if (filled($data['secondary_phone'] ?? null)) {
                $data['secondary_phone'] = $this->phone->normalize($data['secondary_phone']);
            }
            $data['updated_by'] = $actor->id;
            $staffProfile->update($data);
            $staffProfile->user->update([
                'name' => $staffProfile->refresh()->fullName(),
                'phone' => $data['primary_phone'],
            ]);
            $this->log($actor, 'staff_updated', $staffProfile, $old, $data);

            return $staffProfile->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateEmployment(StaffProfile $staffProfile, array $data, User $actor): StaffProfile
    {
        return DB::transaction(function () use ($staffProfile, $data, $actor): StaffProfile {
            $this->validateEmployment($data, $staffProfile->facility_id);
            $old = $staffProfile->employmentRecord?->getAttributes() ?? [];
            $staffProfile->employmentRecord()->updateOrCreate(
                ['staff_profile_id' => $staffProfile->id],
                [
                    ...$data,
                    'facility_id' => $staffProfile->facility_id,
                    'updated_by' => $actor->id,
                    'created_by' => $old === [] ? $actor->id : ($old['created_by'] ?? $actor->id),
                ],
            );
            $staffProfile->user->update([
                'primary_department_id' => $data['primary_department_id'] ?? null,
                'job_title_id' => $data['job_title_id'] ?? null,
            ]);
            $this->departments->setPrimaryDepartment($staffProfile->refresh()->load('user', 'employmentRecord'), $data['primary_department_id'] ?? null, $actor);
            $this->log($actor, 'employment_updated', $staffProfile, $old, $data);

            return $staffProfile->refresh();
        });
    }

    public function activateAccount(StaffProfile $staffProfile, User $actor): void
    {
        $staffProfile->user->update(['status' => UserStatus::Active]);
        $this->log($actor, 'account_activated', $staffProfile, [], ['status' => UserStatus::Active->value]);
    }

    public function suspendAccount(StaffProfile $staffProfile, User $actor, string $reason): void
    {
        if ($actor->id === $staffProfile->user_id) {
            throw ValidationException::withMessages(['status' => 'Huwezi kujisuspend.']);
        }
        $old = ['status' => $staffProfile->user->status?->value];
        $staffProfile->user->update(['status' => UserStatus::Suspended]);
        $this->log($actor, 'account_suspended', $staffProfile, $old, ['status' => UserStatus::Suspended->value, 'reason' => $reason]);
    }

    public function deactivateAccount(StaffProfile $staffProfile, User $actor): void
    {
        $old = ['status' => $staffProfile->user->status?->value];
        $staffProfile->user->update(['status' => UserStatus::Inactive]);
        $this->log($actor, 'account_deactivated', $staffProfile, $old, ['status' => UserStatus::Inactive->value]);
    }

    public function softDeleteStaff(StaffProfile $staffProfile, User $actor): void
    {
        if ($actor->id === $staffProfile->user_id) {
            throw ValidationException::withMessages(['staff' => 'Huwezi kujifuta.']);
        }
        $staffProfile->user->update(['status' => UserStatus::Inactive]);
        $staffProfile->delete();
        $this->log($actor, 'staff_deleted', $staffProfile, $staffProfile->getAttributes(), []);
    }

    public function restoreStaff(StaffProfile $staffProfile, User $actor): void
    {
        $staffProfile->restore();
        $this->log($actor, 'staff_restored', $staffProfile, [], $staffProfile->getAttributes());
    }

    /**
     * @param  array<string, mixed>  $employment
     */
    private function validateEmployment(array $employment, int $facilityId): void
    {
        if (($employment['job_title_id'] ?? null) !== null) {
            $jobTitle = JobTitle::query()->where('facility_id', $facilityId)->find($employment['job_title_id']);
            if ($jobTitle === null) {
                throw ValidationException::withMessages(['job_title_id' => 'Cheo si cha facility hii.']);
            }
        }

        if (($employment['contract_start_date'] ?? null) && ($employment['contract_end_date'] ?? null) && $employment['contract_end_date'] < $employment['contract_start_date']) {
            throw ValidationException::withMessages(['contract_end_date' => 'Tarehe ya mwisho wa mkataba haiwezi kuwa kabla ya mwanzo.']);
        }

        if (($employment['employment_status'] ?? null) === EmploymentStatus::Terminated->value && blank($employment['termination_date'] ?? null)) {
            throw ValidationException::withMessages(['termination_date' => 'Tarehe ya termination inahitajika.']);
        }

        if (($employment['employment_status'] ?? null) === EmploymentStatus::Terminated->value && blank($employment['termination_reason'] ?? null)) {
            throw ValidationException::withMessages(['termination_reason' => 'Sababu ya termination inahitajika.']);
        }
    }

    /**
     * @param  array<string, mixed>  $personal
     */
    private function fullName(array $personal): string
    {
        return collect([$personal['first_name'] ?? null, $personal['middle_name'] ?? null, $personal['last_name'] ?? null])->filter()->implode(' ');
    }

    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    private function log(User $actor, string $event, StaffProfile $staffProfile, array $oldValues, array $newValues): void
    {
        ActivityLog::query()->create([
            'user_id' => $actor->id,
            'event' => $event,
            'subject_type' => StaffProfile::class,
            'subject_id' => $staffProfile->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
