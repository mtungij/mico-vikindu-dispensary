<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\DentalEncounter;
use App\Models\DentalTreatmentPlan;
use App\Models\Service;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DentalTreatmentPlanService
{
    public function __construct(private readonly DentalEncounterNumberService $numbers, private readonly ServicePricingService $pricing) {}
    public function createPlan(DentalEncounter $encounter, array $data, $actor): DentalTreatmentPlan
    {
        abort_unless($encounter->facility_id === currentFacility()?->id, 404);
        return DentalTreatmentPlan::query()->create(['facility_id'=>$encounter->facility_id,'dental_encounter_id'=>$encounter->id,'patient_id'=>$encounter->patient_id,'visit_id'=>$encounter->visit_id,'plan_number'=>$this->numbers->plan($encounter->facility_id),'title'=>$data['title'],'description'=>$data['description'] ?? null,'status'=>'draft','priority'=>$data['priority'] ?? null,'consent_required'=>$data['consent_required'] ?? false,'created_by'=>$actor->id]);
    }
    public function addItem(DentalTreatmentPlan $plan, Service $service, array $data, $actor)
    {
        abort_unless($plan->facility_id === currentFacility()?->id && $service->facility_id === $plan->facility_id, 404);
        $profile = $plan->visit->payerProfile;
        $price = $this->pricing->getCurrentPrice($service, $plan->visit->payer_type, $profile?->insurance_provider_id, $profile?->corporate_account_id);
        if ($service->requires_payment && ! $price) throw ValidationException::withMessages(['service_price'=>"Huduma ya {$service->name} bado haijawekewa bei."]);
        $quantity = (float) ($data['quantity'] ?? 1);
        $item = $plan->items()->create(['service_id'=>$service->id,'tooth_number'=>$data['tooth_number'] ?? null,'surfaces'=>$data['surfaces'] ?? null,'description_snapshot'=>$service->name,'quantity'=>$quantity,'unit_price_snapshot'=>$price?->amount ?? 0,'total_amount'=>((float)($price?->amount ?? 0))*$quantity,'sequence_order'=>$data['sequence_order'] ?? 0,'status'=>'planned','notes'=>$data['notes'] ?? null]);
        $plan->update(['estimated_total'=>$plan->items()->sum('total_amount')]);
        return $item;
    }
    public function accept(DentalTreatmentPlan $plan, $actor): DentalTreatmentPlan { $plan->update(['status'=>'accepted','approved_by'=>$actor->id,'approved_at'=>now()]); $this->audit($actor,'treatment_plan_accepted',$plan); return $plan->refresh(); }
    public function cancel(DentalTreatmentPlan $plan, string $reason, $actor): DentalTreatmentPlan { $plan->update(['status'=>'cancelled','description'=>trim(($plan->description ?? '')."\nCancelled: ".$reason)]); $this->audit($actor,'treatment_plan_cancelled',$plan); return $plan->refresh(); }
    private function audit($actor,string $event,DentalTreatmentPlan $plan): void { ActivityLog::query()->create(['user_id'=>$actor->id,'event'=>$event,'subject_type'=>DentalTreatmentPlan::class,'subject_id'=>$plan->id,'new_values'=>[]]); }
}
