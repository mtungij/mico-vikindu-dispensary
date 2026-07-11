<?php

namespace App\Livewire\Roles;

use App\Models\Permission;
use App\Models\Role;
use App\Services\RolePermissionService;
use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class ManagePermissions extends Component
{
    public Role $role;

    /** @var array<int, string> */
    public array $selectedPermissions = [];

    public function mount(Role $role): void
    {
        Gate::authorize('assignPermissions', $role);
        $this->role = $role->load('permissions');
        $this->selectedPermissions = $this->role->permissions->pluck('name')->values()->all();
    }

    public function save(RolePermissionService $service): void
    {
        Gate::authorize('assignPermissions', $this->role);
        $this->role = $service->syncPermissions($this->role, $this->selectedPermissions, auth()->user());
        Notifier::success('messages.updated');
    }

    public function selectModule(string $module): void
    {
        $names = Permission::query()->where('module', $module)->pluck('name')->all();
        $this->selectedPermissions = collect($this->selectedPermissions)->merge($names)->unique()->values()->all();
    }

    public function clearModule(string $module): void
    {
        $names = Permission::query()->where('module', $module)->pluck('name')->all();
        $this->selectedPermissions = collect($this->selectedPermissions)->diff($names)->values()->all();
    }

    public function render(): View
    {
        $permissions = Permission::query()->orderBy('module')->orderBy('name')->get()->groupBy('module');
        $configuredGroups = collect(config('permissions', []));

        return view('livewire.roles.manage-permissions', [
            'permissions' => $permissions,
            'configuredGroups' => $configuredGroups,
        ])->layout('components.layouts.app', [
            'title' => 'Role Permissions',
            'description' => ($this->role->display_name ?? $this->role->name).' permissions.',
        ]);
    }
}
