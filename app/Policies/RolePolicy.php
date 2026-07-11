<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('roles.view');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->can('roles.view') && $this->belongsToAllowedScope($role);
    }

    public function create(User $user): bool
    {
        return $user->can('roles.create');
    }

    public function update(User $user, Role $role): bool
    {
        return $user->can('roles.update') && $this->belongsToAllowedScope($role) && $role->name !== 'super-admin';
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->can('roles.delete') && $this->belongsToAllowedScope($role) && ! $role->is_system && $role->name !== 'super-admin';
    }

    public function assignPermissions(User $user, Role $role): bool
    {
        return $user->can('roles.assign-permissions') && $this->belongsToAllowedScope($role) && $role->name !== 'super-admin';
    }

    private function belongsToAllowedScope(Role $role): bool
    {
        return $role->facility_id === null || $role->facility_id === currentFacility()?->id;
    }
}
