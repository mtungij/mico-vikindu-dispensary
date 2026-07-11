<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('permissions') as $module => $group) {
            foreach ($group['permissions'] as $name => $label) {
                Permission::query()->updateOrCreate(
                    ['name' => $name, 'guard_name' => 'web'],
                    ['label' => $label, 'module' => $module, 'description' => $label],
                );
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
