<?php

namespace App\Livewire\Rch\FamilyPlanning;

use App\Models\FamilyPlanningClient;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Show extends Component
{
    public FamilyPlanningClient $familyPlanningClient;
    public function mount(FamilyPlanningClient $familyPlanningClient): void { Gate::authorize('rch.family-planning.view'); abort_unless($familyPlanningClient->facility_id === currentFacility()?->id, 404); $this->familyPlanningClient = $familyPlanningClient->load(['patient','currentMethod','visits.selectedMethod','methodEpisodes.method']); }
    public function render(): View { return view('livewire.rch.family-planning.show')->layout('components.layouts.app', ['title'=>$this->familyPlanningClient->fp_client_number]); }
}
