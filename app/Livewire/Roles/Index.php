<?php

namespace App\Livewire\Roles;

use App\Livewire\Forms\RoleForm;
use App\Models\Role;
use App\Services\RolePermissionService;
use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public RoleForm $form;

    public string $search = '';
    public string $status = 'active';
    public bool $showFormModal = false;
    public bool $editing = false;
    public ?int $editingId = null;

    protected $queryString = [
        'search' => ['except' => ''],
        'status' => ['except' => 'active'],
    ];

    public function mount(): void
    {
        Gate::authorize('viewAny', Role::class);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        Gate::authorize('create', Role::class);
        $this->editing = false;
        $this->editingId = null;
        $this->form->resetForm();
        $this->showFormModal = true;
    }

    public function edit(Role $role): void
    {
        Gate::authorize('update', $role);
        $this->editing = true;
        $this->editingId = $role->id;
        $this->form->resetForm();
        $this->form->setRole($role);
        $this->showFormModal = true;
    }

    public function save(RolePermissionService $permissions): void
    {
        $data = $this->form->data();
        $copyFromRoleId = $data['copy_from_role_id'] ?? null;
        unset($data['copy_from_role_id']);

        if ($this->editing && $this->editingId !== null) {
            $role = Role::query()->forCurrentFacility()->findOrFail($this->editingId);
            Gate::authorize('update', $role);
            $role->update([
                ...$data,
                'updated_by' => auth()->id(),
            ]);
            Notifier::success('messages.updated');
        } else {
            Gate::authorize('create', Role::class);
            $role = Role::query()->create([
                ...$data,
                'guard_name' => 'web',
                'facility_id' => currentFacility()?->id,
                'is_system' => false,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);

            if ($copyFromRoleId !== null) {
                $source = Role::query()->forCurrentFacility()->find($copyFromRoleId);
                if ($source !== null) {
                    $permissions->syncPermissions($role, $source->permissions()->pluck('name')->all(), auth()->user());
                }
            }

            Notifier::success('messages.saved');
        }

        $this->closeFormModal();
    }

    public function toggleStatus(Role $role): void
    {
        Gate::authorize('update', $role);
        $role->update([
            'is_active' => ! $role->is_active,
            'updated_by' => auth()->id(),
        ]);
        Notifier::success('messages.updated');
    }

    public function delete(Role $role): void
    {
        Gate::authorize('delete', $role);

        if ($role->users()->exists()) {
            throw ValidationException::withMessages([
                'role' => 'Role hii ina watumishi waliounganishwa.',
            ]);
        }

        DB::transaction(function () use ($role): void {
            $role->syncPermissions([]);
            $role->delete();
        });

        Notifier::success('messages.deleted');
    }

    public function closeFormModal(): void
    {
        $this->showFormModal = false;
        $this->editing = false;
        $this->editingId = null;
        $this->form->resetForm();
    }

    public function resetFilters(): void
    {
        $this->reset(['search']);
        $this->status = 'active';
        $this->resetPage();
    }

    public function render(): View
    {
        $roles = Role::query()
            ->forCurrentFacility()
            ->withCount(['users', 'permissions'])
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($query): void {
                    $query->where('name', 'like', "%{$this->search}%")
                        ->orWhere('display_name', 'like', "%{$this->search}%")
                        ->orWhere('description', 'like', "%{$this->search}%");
                });
            })
            ->when($this->status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($this->status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderByDesc('is_system')
            ->orderBy('display_name')
            ->paginate(10);

        return view('livewire.roles.index', [
            'roles' => $roles,
            'copyRoles' => Role::query()->forCurrentFacility()->where('name', '!=', 'super-admin')->orderBy('display_name')->get(),
        ])->layout('components.layouts.app', [
            'title' => 'Roles',
            'description' => 'Dhibiti majukumu na ruhusa za watumiaji.',
        ]);
    }
}
