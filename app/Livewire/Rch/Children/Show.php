<?php

namespace App\Livewire\Rch\Children;

use App\Models\RchChild;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Show extends Component
{
    public RchChild $rchChild;
    public function mount(RchChild $rchChild): void { Gate::authorize('rch.children.view'); abort_unless($rchChild->facility_id === currentFacility()?->id, 404); $this->rchChild = $rchChild->load(['patient','mother','growthMeasurements.nutritionAssessment','immunizations.vaccine']); }
    public function render(): View { return view('livewire.rch.children.show')->layout('components.layouts.app', ['title'=>$this->rchChild->child_rch_number]); }
}
