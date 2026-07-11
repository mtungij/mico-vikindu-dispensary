<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\User;

class DepartmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('departments.view');
    }

    public function view(User $user, Department $department): bool
    {
        return $user->can('departments.view') && $this->belongsToCurrentFacility($department);
    }

    public function create(User $user): bool
    {
        return $user->can('departments.create');
    }

    public function update(User $user, Department $department): bool
    {
        return $user->can('departments.update') && $this->belongsToCurrentFacility($department);
    }

    public function delete(User $user, Department $department): bool
    {
        return $user->can('departments.delete') && $this->belongsToCurrentFacility($department);
    }

    public function activate(User $user, Department $department): bool
    {
        return $user->can('departments.activate') && $this->belongsToCurrentFacility($department);
    }

    private function belongsToCurrentFacility(Department $department): bool
    {
        return $department->facility_id === currentFacility()?->id;
    }
}
