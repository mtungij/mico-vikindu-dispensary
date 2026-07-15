<?php

namespace App\Livewire\Rch\Children;

use App\Livewire\Forms\Rch\ChildGrowthMeasurementForm;
use App\Models\ChildGrowthMeasurement as GrowthModel;
use App\Models\ChildNutritionAssessment;
use App\Models\RchChild;
use App\Services\ChildGrowthAssessmentService;
use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class GrowthMeasurement extends Component
{
    public RchChild $child; public ChildGrowthMeasurementForm $form;
    public function mount(RchChild $rchChild): void { Gate::authorize('rch.growth.record'); abort_unless($rchChild->facility_id === currentFacility()?->id, 404); $this->child = $rchChild; $this->form->measured_at = now()->toDateTimeString(); }
    public function save(ChildGrowthAssessmentService $service): mixed
    {
        $data = $this->form->normalize();
        $measurement = GrowthModel::query()->create(array_merge($data, ['facility_id'=>$this->child->facility_id,'rch_child_id'=>$this->child->id,'child_patient_id'=>$this->child->child_patient_id,'age_in_days'=>$service->calculateAgeInDays($this->child, $data['measured_at']),'bmi'=>$service->calculateBmi($data['weight_kg'] ?? null, $data['length_height_cm'] ?? null),'recorded_by'=>auth()->id()]));
        $status = $service->assessMuac($measurement);
        $assessment = ChildNutritionAssessment::query()->create(['facility_id'=>$measurement->facility_id,'rch_child_id'=>$this->child->id,'child_growth_measurement_id'=>$measurement->id,'assessment_date'=>today(),'weight_for_age_classification'=>$service->assessWeightForAge($measurement),'bmi_for_age_classification'=>$service->assessBmiForAge($measurement),'muac_classification'=>$status,'edema_classification'=>$measurement->edema_present ? 'severe_acute_malnutrition' : 'normal','overall_nutrition_status'=>$measurement->edema_present ? 'severe_acute_malnutrition' : $status,'referral_required'=>in_array($status, ['severe_acute_malnutrition','moderate_acute_malnutrition'], true),'assessed_by'=>auth()->id()]);
        $service->buildNutritionAlerts($measurement, $assessment);
        Notifier::success('Growth measurement recorded.');
        return redirect()->route('rch.children.growth', $this->child);
    }
    public function render(): View { return view('livewire.rch.children.growth-measurement')->layout('components.layouts.app', ['title'=>'Record Growth Measurement']); }
}
