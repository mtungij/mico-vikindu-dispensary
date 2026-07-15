<?php

namespace App\Livewire\Rch\Children;

use App\Models\ChildNutritionAssessment;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class NutritionAssessment extends Component
{
    use WithPagination;
    public function mount(): void { Gate::authorize('rch.growth.assess-nutrition'); }
    public function render(): View { $assessments = ChildNutritionAssessment::query()->forCurrentFacility()->with('child.patient')->latest()->paginate(15); return view('livewire.rch.children.nutrition-assessment', compact('assessments'))->layout('components.layouts.app', ['title'=>'Nutrition Alerts']); }
}
