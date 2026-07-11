<?php

namespace App\Services;

use App\Models\Department;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StaffDepartmentService
{
    /**
     * @param  array<int, array<string, mixed>>  $departments
     */
    public function syncDepartments(StaffProfile $staffProfile, ?int $primaryDepartmentId, array $departments, User $actor): void
    {
        $facilityId = $staffProfile->facility_id;
        $ids = collect($departments)->pluck('department_id')->filter()->push($primaryDepartmentId)->filter()->unique()->values();
        $this->validateDepartmentsBelongToFacility($ids->all(), $facilityId);

        DB::transaction(function () use ($staffProfile, $primaryDepartmentId, $departments, $actor, $ids): void {
            $sync = [];

            foreach ($ids as $departmentId) {
                $row = collect($departments)->firstWhere('department_id', $departmentId) ?? [];
                $sync[$departmentId] = [
                    'is_primary' => (int) $departmentId === (int) $primaryDepartmentId,
                    'can_receive_queue' => (bool) ($row['can_receive_queue'] ?? false),
                    'can_manage_department' => (bool) ($row['can_manage_department'] ?? false),
                    'assigned_by' => $actor->id,
                    'assigned_at' => now(),
                ];
            }

            $staffProfile->user->departments()->sync($sync);
            $this->setPrimaryDepartment($staffProfile, $primaryDepartmentId, $actor);
        });
    }

    public function setPrimaryDepartment(StaffProfile $staffProfile, ?int $departmentId, User $actor): void
    {
        if ($departmentId !== null) {
            $this->validateDepartmentsBelongToFacility([$departmentId], $staffProfile->facility_id);
        }

        foreach ($staffProfile->user->departments()->pluck('departments.id')->all() as $existingDepartmentId) {
            $staffProfile->user->departments()->updateExistingPivot($existingDepartmentId, ['is_primary' => false]);
        }

        if ($departmentId !== null && $staffProfile->user->departments()->where('departments.id', $departmentId)->exists()) {
            $staffProfile->user->departments()->updateExistingPivot($departmentId, [
                'is_primary' => true,
                'assigned_by' => $actor->id,
                'assigned_at' => now(),
            ]);
        }

        $staffProfile->user->update(['primary_department_id' => $departmentId]);
        $staffProfile->employmentRecord?->update(['primary_department_id' => $departmentId, 'updated_by' => $actor->id]);
    }

    /**
     * @param  array<int, int>  $departmentIds
     */
    public function validateDepartmentsBelongToFacility(array $departmentIds, int $facilityId): void
    {
        $departmentIds = array_values(array_filter($departmentIds));
        if ($departmentIds === []) {
            return;
        }

        $count = Department::query()->where('facility_id', $facilityId)->whereIn('id', $departmentIds)->count();

        if ($count !== count(array_unique($departmentIds))) {
            throw ValidationException::withMessages([
                'departments' => 'Department si ya facility hii.',
            ]);
        }
    }
}
