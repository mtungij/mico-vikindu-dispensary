<?php

namespace App\Livewire\JobTitles;

use App\Enums\EducationLevel;
use App\Enums\EmploymentCategory;
use App\Livewire\Forms\JobTitleForm;
use App\Models\Department;
use App\Models\JobTitle;
use App\Services\JobTitleService;
use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public JobTitleForm $form;

    public string $search = '';
    public string $status = 'active';
    public string $department = '';
    public bool $showFormModal = false;
    public bool $editing = false;
    public ?int $editingId = null;

    protected $queryString = [
        'search' => ['except' => ''],
        'status' => ['except' => 'active'],
        'department' => ['except' => ''],
    ];

    public function mount(): void
    {
        Gate::authorize('viewAny', JobTitle::class);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function updatingDepartment(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        Gate::authorize('create', JobTitle::class);
        $this->editing = false;
        $this->editingId = null;
        $this->form->resetForm();
        $this->showFormModal = true;
    }

    public function edit(JobTitle $jobTitle): void
    {
        Gate::authorize('update', $jobTitle);
        $this->editing = true;
        $this->editingId = $jobTitle->id;
        $this->form->resetForm();
        $this->form->setJobTitle($jobTitle);
        $this->showFormModal = true;
    }

    public function save(JobTitleService $service): void
    {
        $data = $this->form->data();

        if ($this->editing && $this->editingId !== null) {
            $jobTitle = JobTitle::query()->forCurrentFacility()->findOrFail($this->editingId);
            Gate::authorize('update', $jobTitle);
            $service->update($jobTitle, $data, auth()->user());
            Notifier::success('messages.updated');
        } else {
            Gate::authorize('create', JobTitle::class);
            $service->create($data, auth()->user());
            Notifier::success('messages.saved');
        }

        $this->closeFormModal();
    }

    public function toggleStatus(JobTitle $jobTitle, JobTitleService $service): void
    {
        Gate::authorize('activate', $jobTitle);
        $service->toggleStatus($jobTitle, auth()->user());
        Notifier::success('messages.updated');
    }

    public function delete(JobTitle $jobTitle, JobTitleService $service): void
    {
        Gate::authorize('delete', $jobTitle);

        try {
            $service->delete($jobTitle, auth()->user());
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
        $this->reset(['search', 'department']);
        $this->status = 'active';
        $this->resetPage();
    }

    public function render(): View
    {
        $departments = Department::query()->forCurrentFacility()->where('is_active', true)->orderBy('name')->get();
        $jobTitles = JobTitle::query()
            ->forCurrentFacility()
            ->with('department')
            ->withCount('users')
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($query): void {
                    $query->where('name', 'like', "%{$this->search}%")
                        ->orWhere('code', 'like', "%{$this->search}%")
                        ->orWhere('license_authority', 'like', "%{$this->search}%");
                });
            })
            ->when($this->status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($this->status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($this->department !== '', fn ($query) => $query->where('department_id', $this->department))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(10);

        return view('livewire.job-titles.index', [
            'jobTitles' => $jobTitles,
            'departments' => $departments,
            'employmentCategories' => EmploymentCategory::cases(),
            'educationLevels' => EducationLevel::cases(),
        ])->layout('components.layouts.app', [
            'title' => 'Job Titles',
            'description' => 'Dhibiti vyeo, sifa, na uhusiano wake na departments.',
        ]);
    }
}
