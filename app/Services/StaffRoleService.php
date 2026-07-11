<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

class StaffRoleService
{
    /**
     * @param  array<int, string|int>  $roleIds
     */
    public function syncRoles(StaffProfile $staffProfile, array $roleIds, User $actor): void
    {
        $roles = Role::query()->forCurrentFacility()->whereIn('id', $roleIds)->get();
        $this->validateAssignableRoles($roles, $actor);

        DB::transaction(function () use ($staffProfile, $roles): void {
            $staffProfile->user->syncRoles($roles->pluck('name')->all());
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        });
    }

    /**
     * @param  array<int, string>  $permissionNames
     */
    public function syncDirectPermissions(StaffProfile $staffProfile, array $permissionNames, User $actor): void
    {
        if (! $actor->can('staff.assign-direct-permission')) {
            throw ValidationException::withMessages(['permissions' => 'Huna ruhusa ya direct permissions.']);
        }

        $permissionNames = collect($permissionNames)->filter()->unique()->values();
        $this->validateAssignablePermissions($permissionNames->all(), $actor);
        $old = $staffProfile->user->getDirectPermissions()->pluck('name')->sort()->values()->all();
        $staffProfile->user->syncPermissions($permissionNames->all());
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        \App\Models\ActivityLog::query()->create([
            'user_id' => $actor->id,
            'event' => 'direct_permissions_updated',
            'subject_type' => User::class,
            'subject_id' => $staffProfile->user_id,
            'old_values' => ['permissions' => $old],
            'new_values' => ['permissions' => $permissionNames->sort()->values()->all()],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    public function validateAssignableRoles($roles, User $actor): void
    {
        foreach ($roles as $role) {
            if (! $actor->is_super_admin && $role->name === 'super-admin') {
                throw ValidationException::withMessages(['roles' => 'Huwezi kuassign Super Admin role.']);
            }

            if (! $actor->is_super_admin) {
                foreach ($role->permissions as $permission) {
                    if (! $actor->can($permission->name)) {
                        throw ValidationException::withMessages(['roles' => 'Huwezi kuassign role yenye permissions ambazo huna.']);
                    }
                }
            }
        }
    }

    /**
     * @param  array<int, string>  $permissionNames
     */
    public function validateAssignablePermissions(array $permissionNames, User $actor): void
    {
        $existing = Permission::query()->whereIn('name', $permissionNames)->pluck('name')->all();
        if (count($existing) !== count($permissionNames)) {
            throw ValidationException::withMessages(['permissions' => 'Permission haipo.']);
        }

        if (! $actor->is_super_admin) {
            foreach ($permissionNames as $permissionName) {
                if (! $actor->can($permissionName)) {
                    throw ValidationException::withMessages(['permissions' => 'Huwezi kuassign permission ambayo huna.']);
                }
            }
        }
    }
}
