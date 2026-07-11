<?php

namespace App\Livewire\Permissions;

use App\Models\Permission;
use App\Services\RolePermissionService;
use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Index extends Component
{
    public string $search = '';
    public string $module = '';

    public function mount(): void
    {
        Gate::authorize('permissions.view');
    }

    public function sync(RolePermissionService $service): void
    {
        Gate::authorize('permissions.view');
        $service->syncConfiguredPermissions();
        Notifier::success('messages.updated');
    }

    public function render(): View
    {
        $permissions = Permission::query()
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($query): void {
                    $query->where('name', 'like', "%{$this->search}%")
                        ->orWhere('label', 'like', "%{$this->search}%")
                        ->orWhere('description', 'like', "%{$this->search}%");
                });
            })
            ->when($this->module !== '', fn ($query) => $query->where('module', $this->module))
            ->orderBy('module')
            ->orderBy('name')
            ->get()
            ->groupBy('module');

        return view('livewire.permissions.index', [
            'permissions' => $permissions,
            'modules' => Permission::query()->whereNotNull('module')->distinct()->orderBy('module')->pluck('module'),
        ])->layout('components.layouts.app', [
            'title' => 'Permissions',
            'description' => 'Orodha ya ruhusa za mfumo kulingana na module.',
        ]);
    }
}
