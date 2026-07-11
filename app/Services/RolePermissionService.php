<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionService
{
    /**
     * @param  array<int, string>  $permissionNames
     */
    public function syncPermissions(Role $role, array $permissionNames, User $user): Role
    {
        if ($role->name === 'super-admin') {
            throw ValidationException::withMessages([
                'role' => 'Super Admin role haitakiwi kubadilishwa kupitia ukurasa huu.',
            ]);
        }

        $permissionNames = collect($permissionNames)->filter()->unique()->values()->all();

        if (! $user->is_super_admin) {
            $unauthorized = collect($permissionNames)->reject(fn (string $name): bool => $user->can($name));
            if ($unauthorized->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'permissions' => 'Huwezi kumpa role permissions ambazo huna.',
                ]);
            }
        }

        return DB::transaction(function () use ($role, $permissionNames, $user): Role {
            $oldValues = ['permissions' => $role->permissions()->pluck('name')->sort()->values()->all()];
            $role->syncPermissions($permissionNames);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $newValues = ['permissions' => collect($permissionNames)->sort()->values()->all()];

            ActivityLog::query()->create([
                'user_id' => $user->id,
                'event' => 'role.permissions_synced',
                'subject_type' => Role::class,
                'subject_id' => $role->id,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return $role->refresh()->load('permissions');
        });
    }

    public function syncConfiguredPermissions(): int
    {
        $count = 0;

        foreach (config('permissions', []) as $module => $group) {
            foreach ($group['permissions'] ?? [] as $name => $label) {
                Permission::query()->updateOrCreate(
                    ['name' => $name, 'guard_name' => 'web'],
                    [
                        'label' => $label,
                        'module' => $module,
                        'description' => $group['label'] ?? null,
                    ],
                );
                $count++;
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $count;
    }
}
