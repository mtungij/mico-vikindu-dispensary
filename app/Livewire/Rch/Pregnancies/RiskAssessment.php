<?php

namespace App\Livewire\Rch\Pregnancies;

use App\Models\Pregnancy;
use App\Services\PregnancyRiskAssessmentService;
use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class RiskAssessment extends Component
{
    public Pregnancy $pregnancy; public array $codes = [];
    public function mount(Pregnancy $pregnancy): void { Gate::authorize('rch.pregnancies.manage-risk'); abort_unless($pregnancy->facility_id === currentFacility()?->id, 404); $this->pregnancy = $pregnancy; }
    public function assess(PregnancyRiskAssessmentService $service): void { $service->assessPregnancy($this->pregnancy, null, ['codes'=>$this->codes], auth()->user()); $this->pregnancy->refresh(); Notifier::success('Risk assessment updated.'); }
    public function render(): View { return view('livewire.rch.pregnancies.risk-assessment', ['riskTypes'=>\App\Models\PregnancyRiskFactorType::query()->where(fn($q)=>$q->whereNull('facility_id')->orWhere('facility_id', currentFacility()?->id))->where('is_active', true)->orderBy('sort_order')->get()])->layout('components.layouts.app', ['title'=>'Pregnancy Risk Assessment']); }
}
