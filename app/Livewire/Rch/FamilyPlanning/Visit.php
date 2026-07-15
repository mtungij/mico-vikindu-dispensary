<?php

namespace App\Livewire\Rch\FamilyPlanning;

use App\Livewire\Forms\Rch\FamilyPlanningVisitForm;
use App\Models\FamilyPlanningClient;
use App\Models\FamilyPlanningMethod;
use App\Services\FamilyPlanningService;
use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Visit extends Component
{
    public FamilyPlanningClient $client; public FamilyPlanningVisitForm $form;
    public function mount(FamilyPlanningClient $familyPlanningClient): void { Gate::authorize('rch.family-planning.record-visit'); abort_unless($familyPlanningClient->facility_id === currentFacility()?->id, 404); $this->client = $familyPlanningClient; $this->form->visit_date = today()->toDateString(); }
    public function save(FamilyPlanningService $service): mixed { $service->recordVisit($this->client, $this->form->normalize(), auth()->user()); Notifier::success('FP visit recorded.'); return redirect()->route('rch.family-planning.show', $this->client); }
    public function render(): View { return view('livewire.rch.family-planning.visit', ['methods'=>FamilyPlanningMethod::query()->where(fn($q)=>$q->whereNull('facility_id')->orWhere('facility_id', currentFacility()?->id))->where('is_active', true)->get()])->layout('components.layouts.app', ['title'=>'Record FP Visit']); }
}
