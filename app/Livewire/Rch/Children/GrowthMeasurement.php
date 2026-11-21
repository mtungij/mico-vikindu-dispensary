<?php

namespace App\Livewire\Rch\Children;

use App\Enums\RchService;
use App\Livewire\Forms\Rch\ChildGrowthMeasurementForm;
use App\Models\ChildGrowthMeasurement as GrowthModel;
use App\Models\ChildNutritionAssessment;
use App\Models\Patient;
use App\Models\RchChild;
use App\Models\RchEncounter;
use App\Services\ChildGrowthAssessmentService;
use App\Services\RchProgrammeVisitService;
use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class GrowthMeasurement extends Component
{
    public ?RchChild $child = null;

    public ?RchEncounter $linkedEncounter = null;

    public ?GrowthModel $record = null;

    public ChildGrowthMeasurementForm $form;

    public function mount(?RchChild $rchChild = null, ?int $linkedVisit = null): void
    {
        Gate::authorize('rch.growth.record');
        if ($linkedVisit !== null) {
            $this->linkedEncounter = app(RchProgrammeVisitService::class)->open($linkedVisit, RchService::ChildMonitoring, auth()->user());
            $this->record = $this->linkedRecord($this->linkedEncounter);
            $this->child = RchChild::query()->forCurrentFacility()->where('child_patient_id', $this->linkedEncounter->patient_id)->first();
            if ($this->record) {
                $this->form->fillFromModel($this->record);
                $this->form->measured_at = $this->record->measured_at->format('Y-m-d\TH:i');

                return;
            }
            $triage = $this->linkedEncounter->visit->latestCompletedTriageAssessment;
            $this->form->weight_kg = $triage?->weight_kg;
            $this->form->muac_cm = $triage?->muac_cm;
            $this->form->length_height_cm = $triage?->height_cm;
        } else {
            abort_unless($rchChild?->facility_id === currentFacility()?->id && auth()->user()->belongsToCurrentFacility(), 404);
            $this->child = $rchChild;
        }
        $this->form->measured_at = now()->format('Y-m-d\TH:i');
    }

    public function save(ChildGrowthAssessmentService $service, RchProgrammeVisitService $programmes): mixed
    {
        Gate::authorize('rch.growth.record');
        if ($this->linkedEncounter) {
            $programmes->save($this->linkedEncounter, RchService::ChildMonitoring, auth()->user(), function (RchEncounter $encounter, $visit, Patient $patient) use ($service, $programmes): void {
                if ($this->linkedRecord($encounter)) {
                    return;
                }
                $data = $this->form->normalize();
                $child = $programmes->childProfile($patient, auth()->user());
                $this->recordMeasurement($child, [...$data, 'visit_id' => $visit->id, 'rch_encounter_id' => $encounter->id], $service);
            });
            Notifier::success(__('rch.visit_saved'));

            return redirect()->route('rch.visits.child-growth', $this->linkedEncounter->visit_id);
        }

        abort_unless($this->child?->facility_id === currentFacility()?->id && auth()->user()->belongsToCurrentFacility(), 404);
        $this->recordMeasurement($this->child, $this->form->normalize(), $service);
        Notifier::success('Growth measurement recorded.');

        return redirect()->route('rch.children.growth', $this->child);
    }

    private function linkedRecord(RchEncounter $encounter): ?GrowthModel
    {
        return GrowthModel::query()->forCurrentFacility()->where('rch_encounter_id', $encounter->id)
            ->where('child_patient_id', $encounter->patient_id)->latest('id')->first();
    }

    private function recordMeasurement(RchChild $child, array $data, ChildGrowthAssessmentService $service): void
    {
        $measurement = GrowthModel::query()->create([
            ...$data, 'facility_id' => $child->facility_id, 'rch_child_id' => $child->id, 'child_patient_id' => $child->child_patient_id,
            'age_in_days' => $service->calculateAgeInDays($child, $data['measured_at']),
            'bmi' => $service->calculateBmi($data['weight_kg'] ?? null, $data['length_height_cm'] ?? null), 'recorded_by' => auth()->id(),
        ]);
        $status = $service->assessMuac($measurement);
        $assessment = ChildNutritionAssessment::query()->create(['facility_id' => $measurement->facility_id, 'rch_child_id' => $child->id, 'child_growth_measurement_id' => $measurement->id, 'assessment_date' => today(), 'weight_for_age_classification' => $service->assessWeightForAge($measurement), 'bmi_for_age_classification' => $service->assessBmiForAge($measurement), 'muac_classification' => $status, 'edema_classification' => $measurement->edema_present ? 'severe_acute_malnutrition' : 'normal', 'overall_nutrition_status' => $measurement->edema_present ? 'severe_acute_malnutrition' : $status, 'referral_required' => in_array($status, ['severe_acute_malnutrition', 'moderate_acute_malnutrition'], true), 'assessed_by' => auth()->id()]);
        $service->buildNutritionAlerts($measurement, $assessment);
    }

    public function render(): View
    {
        if ($this->linkedEncounter) {
            app(RchProgrammeVisitService::class)->authorizeContext($this->linkedEncounter, RchService::ChildMonitoring, auth()->user());
        } else {
            Gate::authorize('rch.growth.record');
            abort_unless($this->child?->facility_id === currentFacility()?->id && auth()->user()->belongsToCurrentFacility(), 404);
        }

        return view('livewire.rch.children.growth-measurement')->layout('components.layouts.app', ['title' => 'Record Growth Measurement']);
    }
}
