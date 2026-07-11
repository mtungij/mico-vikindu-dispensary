<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DepartmentService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $user): Department
    {
        return DB::transaction(function () use ($data, $user): Department {
            $data['facility_id'] = currentFacility()?->id;
            $data['code'] = str($data['code'])->upper()->toString();
            $data['created_by'] = $user->id;
            $data['updated_by'] = $user->id;

            $department = Department::query()->create($data);
            $this->log($user, 'department.created', $department, [], $department->getAttributes());

            return $department;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Department $department, array $data, User $user): Department
    {
        return DB::transaction(function () use ($department, $data, $user): Department {
            $oldValues = $department->only(array_keys($data));
            $data['code'] = str($data['code'])->upper()->toString();
            $data['updated_by'] = $user->id;

            $department->update($data);
            $this->log($user, 'department.updated', $department, $oldValues, $data);

            return $department->refresh();
        });
    }

    public function toggleStatus(Department $department, User $user): Department
    {
        return DB::transaction(function () use ($department, $user): Department {
            $oldValues = ['is_active' => $department->is_active];
            $department->update([
                'is_active' => ! $department->is_active,
                'updated_by' => $user->id,
            ]);
            $this->log($user, 'department.status_changed', $department, $oldValues, ['is_active' => $department->is_active]);

            return $department->refresh();
        });
    }

    public function delete(Department $department, User $user): void
    {
        if ($department->users()->exists() || $department->jobTitles()->exists()) {
            throw ValidationException::withMessages([
                'department' => 'Department hii ina watumishi au vyeo vilivyounganishwa.',
            ]);
        }

        DB::transaction(function () use ($department, $user): void {
            $oldValues = $department->getAttributes();
            $department->update(['updated_by' => $user->id]);
            $department->delete();
            $this->log($user, 'department.deleted', $department, $oldValues, []);
        });
    }

    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    private function log(User $user, string $event, Department $department, array $oldValues, array $newValues): void
    {
        ActivityLog::query()->create([
            'user_id' => $user->id,
            'event' => $event,
            'subject_type' => Department::class,
            'subject_id' => $department->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
