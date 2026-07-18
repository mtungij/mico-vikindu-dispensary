<?php

namespace App\Livewire\Departments;

use App\Enums\DepartmentType;
use App\Livewire\Forms\DepartmentForm;
use App\Models\Department;
use App\Models\WorkflowSetting;
use App\Services\DepartmentService;
use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public DepartmentForm $form;

    public string $search = '';
    public string $status = 'active';
    public string $type = '';
    public bool $showFormModal = false;
    public bool $editing = false;
    public ?int $editingId = null;

    protected $queryString = [
        'search' => ['except' => ''],
        'status' => ['except' => 'active'],
        'type' => ['except' => ''],
    ];

    public function mount(): void
    {
        Gate::authorize('viewAny', Department::class);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function updatingType(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        Gate::authorize('create', Department::class);
        $this->editing = false;
        $this->editingId = null;
        $this->form->resetForm();
        $this->showFormModal = true;
    }

    public function edit(Department $department): void
    {
        Gate::authorize('update', $department);
        $this->editing = true;
        $this->editingId = $department->id;
        $this->form->resetForm();
        $this->form->setDepartment($department);
        $this->showFormModal = true;
    }

    public function save(DepartmentService $service): void
    {
        $data = $this->form->data();

        if ($this->editing && $this->editingId !== null) {
            $department = Department::query()->forCurrentFacility()->findOrFail($this->editingId);
            Gate::authorize('update', $department);
            $service->update($department, $data, auth()->user());
            Notifier::success('messages.updated');
        } else {
            Gate::authorize('create', Department::class);
            $service->create($data, auth()->user());
            Notifier::success('messages.saved');
        }

        $this->closeFormModal();
    }

    public function toggleStatus(Department $department, DepartmentService $service): void
    {
        Gate::authorize('activate', $department);
        $service->toggleStatus($department, auth()->user());
        Notifier::success('messages.updated');
    }

    public function delete(Department $department, DepartmentService $service): void
    {
        Gate::authorize('delete', $department);

        try {
            $service->delete($department, auth()->user());
            Notifier::success('messages.deleted');
        } catch (ValidationException $exception) {
            $this->addError('delete', collect($exception->errors())->flatten()->first());
            Notifier::error('messages.failed');
        }
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
        $this->reset(['search', 'type']);
        $this->status = 'active';
        $this->resetPage();
    }

    public function render(): View
    {
        $departments = Department::query()
            ->forCurrentFacility()
            ->withCount(['jobTitles', 'users'])
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($query): void {
                    $query->where('name', 'like', "%{$this->search}%")
                        ->orWhere('code', 'like', "%{$this->search}%")
                        ->orWhere('location', 'like', "%{$this->search}%");
                });
            })
            ->when($this->status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($this->status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($this->type !== '', fn ($query) => $query->where('department_type', $this->type))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(10);

        return view('livewire.departments.index', [
            'departments' => $departments,
            'types' => DepartmentType::cases(),
            'workflowFlags' => [
                'payment_before_consultation' => $this->workflowFlag('payment_before_consultation', true),
                'auto_queue_after_payment' => true,
                'allow_emergency_bypass' => $this->workflowFlag('allow_emergency_override', true),
            ],
        ])->layout('components.layouts.app', [
            'title' => 'Departments',
            'description' => 'Dhibiti vitengo vya huduma, fedha, na utawala.',
        ]);
    }

    private function workflowFlag(string $key, bool $default): bool
    {
        $value = WorkflowSetting::query()
            ->forCurrentFacility()
            ->where('key', $key)
            ->value('value');

        if ($value === null) {
            return $default;
        }

        if (is_array($value)) {
            return (bool) ($value[0] ?? reset($value));
        }

        return filter_var($value, FILTER_VALIDATE_BOOL);
    }
}
