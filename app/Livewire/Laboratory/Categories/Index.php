<?php

namespace App\Livewire\Laboratory\Categories;

use App\Livewire\Forms\LaboratoryTestCategoryForm;
use App\Models\ActivityLog;
use App\Models\LaboratoryTestCategory;
use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;
    public LaboratoryTestCategoryForm $form;
    public bool $showModal = false;
    public ?LaboratoryTestCategory $editing = null;

    public function mount(): void { Gate::authorize('laboratory.manage-test-categories'); }
    public function create(): void { $this->editing = null; $this->form->resetForm(); $this->showModal = true; }
    public function edit(LaboratoryTestCategory $category): void { abort_unless($category->facility_id === currentFacility()?->id, 404); $this->editing = $category; $this->form->fillFromModel($category); $this->showModal = true; }
    public function save(): void
    {
        Gate::authorize('laboratory.manage-test-categories');
        $this->form->validate();
        DB::transaction(function (): void {
            $category = LaboratoryTestCategory::query()->updateOrCreate(['id' => $this->editing?->id], [...$this->form->normalize(), 'facility_id' => currentFacility()->id, 'created_by' => $this->editing?->created_by ?? auth()->id(), 'updated_by' => auth()->id()]);
            ActivityLog::query()->create(['user_id' => auth()->id(), 'event' => 'laboratory_category_created', 'subject_type' => $category::class, 'subject_id' => $category->id]);
        });
        $this->showModal = false; Notifier::success('messages.saved');
    }
    public function render(): View { return view('livewire.laboratory.categories.index', ['categories' => LaboratoryTestCategory::query()->forCurrentFacility()->withCount('tests')->orderBy('sort_order')->paginate(10)])->layout('components.layouts.app', ['title' => 'Laboratory Test Categories', 'description' => 'Sanidi makundi ya vipimo vya maabara.']); }
}
