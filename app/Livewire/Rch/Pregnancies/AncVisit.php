<?php

namespace App\Livewire\Rch\Pregnancies;

use App\Livewire\Forms\Rch\AncVisitForm;
use App\Models\AncVisit as AncVisitModel;
use App\Models\Pregnancy;
use App\Services\PregnancyDatingService;
use App\Services\PregnancyRiskAssessmentService;
use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class AncVisit extends Component
{
    public Pregnancy $pregnancy; public AncVisitForm $form;
    public function mount(Pregnancy $pregnancy): void { Gate::authorize('rch.anc.record-visit'); abort_unless($pregnancy->facility_id === currentFacility()?->id, 404); $this->pregnancy = $pregnancy; $this->form->visit_date = today()->toDateString(); }
    public function save(PregnancyDatingService $dating, PregnancyRiskAssessmentService $risk): mixed
    {
        $data = $this->form->normalize();
        $ga = $this->pregnancy->lmp_date ? $dating->calculateGestationalAge($this->pregnancy->lmp_date, $data['visit_date']) : ['weeks'=>0,'days'=>0];
        $visit = AncVisitModel::query()->create(array_merge($data, ['facility_id'=>$this->pregnancy->facility_id,'pregnancy_id'=>$this->pregnancy->id,'patient_id'=>$this->pregnancy->patient_id,'anc_visit_number'=>$this->pregnancy->ancVisits()->count()+1,'gestational_age_weeks'=>$ga['weeks'],'gestational_age_days'=>$ga['days'],'reviewed_by'=>auth()->id()]));
        $risk->assessPregnancy($this->pregnancy, $visit, $data, auth()->user());
        Notifier::success('ANC visit recorded.');
        return redirect()->route('rch.pregnancies.show', $this->pregnancy);
    }
    public function render(): View { return view('livewire.rch.pregnancies.anc-visit')->layout('components.layouts.app', ['title'=>'Record ANC Visit']); }
}
