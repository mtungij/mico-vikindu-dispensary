<?php

namespace App\Services;

use App\Models\DentalEncounter;
use App\Models\DentalProcedure;
use App\Models\DentalTreatmentPlan;
use App\Models\StaffSignature;
use Illuminate\Validation\ValidationException;

class DentalReportService
{
    public function providerSignature($user): ?StaffSignature
    {
        return StaffSignature::query()->where('facility_id', currentFacility()?->id)->whereHas('staff', fn($q)=>$q->where('user_id', $user->id))->where('is_active', true)->latest('uploaded_at')->first();
    }
    public function assertSignatureIfRequired($provider): void
    {
        $required = \App\Models\FacilitySetting::query()->where('facility_id', currentFacility()?->id)->where('key', 'dental_require_signature_for_report')->value('value');
        if (filter_var($required, FILTER_VALIDATE_BOOLEAN) && ! $this->providerSignature($provider)) throw ValidationException::withMessages(['signature'=>'Provider hana active staff signature.']);
    }
    public function chartData(DentalEncounter $encounter): array { return ['encounter'=>$encounter->load(['patient','provider','toothRecords.findings.type','diagnoses','treatmentPlans.items','procedures.materials.material'])]; }
    public function planData(DentalTreatmentPlan $plan): array { return ['plan'=>$plan->load(['patient','encounter.provider','items.service'])]; }
    public function procedureData(DentalProcedure $procedure): array { return ['procedure'=>$procedure->load(['patient','encounter.provider','materials.material'])]; }
}
