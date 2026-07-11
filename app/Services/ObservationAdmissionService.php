<?php

namespace App\Services;

use App\Enums\BedReservationStatus;
use App\Enums\ObservationAdmissionStatus;
use App\Enums\VisitStatus;
use App\Models\ActivityLog;
use App\Models\Bed;
use App\Models\BedReservation;
use App\Models\ObservationAdmission;
use App\Models\Patient;
use App\Models\Service;
use App\Models\Visit;
use App\Models\VisitMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ObservationAdmissionService
{
    public function __construct(private readonly ObservationAdmissionNumberService $numbers, private readonly BedManagementService $beds, private readonly ObservationBillingService $billing) {}
    public function admit(Patient $patient, Visit $visit, array $data, $actor): ObservationAdmission
    {
        return DB::transaction(function () use ($patient, $visit, $data, $actor): ObservationAdmission {
            $this->validateActiveAdmission($patient);
            if ($visit->facility_id !== currentFacility()->id || $visit->patient_id !== $patient->id) throw ValidationException::withMessages(['visit_id'=>'Visit na patient haviendani.']);
            if (in_array($visit->visit_status?->value ?? $visit->visit_status, ['completed','cancelled','discharged','referred'], true)) throw ValidationException::withMessages(['visit_id'=>'Visit si active.']);
            $this->validatePayment($visit, $actor, $data['override_reason'] ?? null);
            $bed = ! empty($data['bed_id']) ? Bed::query()->where('facility_id', currentFacility()->id)->findOrFail($data['bed_id']) : null;
            $reservation = $bed ? BedReservation::query()->where('bed_id',$bed->id)->where('patient_id',$patient->id)->where('status', BedReservationStatus::Active)->first() : null;
            $status = $bed ? ObservationAdmissionStatus::Admitted : ObservationAdmissionStatus::AwaitingBed;
            $admission = ObservationAdmission::query()->create([
                'facility_id'=>currentFacility()->id,'patient_id'=>$patient->id,'visit_id'=>$visit->id,'clinical_encounter_id'=>$data['clinical_encounter_id'] ?? null,'admission_number'=>$this->numbers->next(currentFacility()->id),'admission_type'=>$data['admission_type'],'reason_for_admission'=>$data['reason_for_admission'],'provisional_diagnosis'=>$data['provisional_diagnosis'] ?? null,'admitted_by'=>$actor->id,'admitted_at'=>$data['admitted_at'] ?? now(),'expected_discharge_at'=>$data['expected_discharge_at'] ?? null,'payer_type'=>$visit->payer_type,'patient_payer_profile_id'=>$visit->patient_payer_profile_id,'status'=>$status,'acuity_level'=>$data['acuity_level'] ?? null,'isolation_required'=>(bool)($data['isolation_required'] ?? false),'guardian_required'=>(bool)($data['guardian_required'] ?? false),'guardian_name'=>$data['guardian_name'] ?? null,'guardian_phone'=>$data['guardian_phone'] ?? null,'diet_instruction'=>$data['diet_instruction'] ?? null,'mobility_status'=>$data['mobility_status'] ?? null,'fall_risk'=>$data['fall_risk'] ?? null,'infection_risk'=>$data['infection_risk'] ?? null,'allergies_snapshot'=>$data['allergies_snapshot'] ?? null,'chronic_conditions_snapshot'=>$data['chronic_conditions_snapshot'] ?? null,'notes'=>$data['notes'] ?? null,'created_by'=>$actor->id,
            ]);
            if ($bed) $this->beds->assignBed($admission, $bed, $actor, $reservation);
            $visit->update(['visit_status' => $bed ? VisitStatus::AwaitingBed : VisitStatus::AwaitingBed, 'updated_by'=>$actor->id]);
            $this->createInitialMovement($admission, $actor);
            if (! empty($data['service_id'])) $this->billing->createAdmissionCharges($admission, Service::query()->where('facility_id', currentFacility()->id)->find($data['service_id']), $actor);
            ActivityLog::query()->create(['user_id'=>$actor->id,'event'=>'observation_admitted','subject_type'=>$admission::class,'subject_id'=>$admission->id]);
            return $admission->refresh();
        });
    }
    public function validateActiveAdmission(Patient $patient): void { if (ObservationAdmission::query()->where('facility_id', $patient->facility_id)->where('patient_id',$patient->id)->whereIn('status',['awaiting_payment','awaiting_bed','admitted','under_observation','ready_for_discharge'])->exists()) throw ValidationException::withMessages(['patient_id'=>'Patient ana active observation admission.']); }
    public function validatePayment(Visit $visit, $actor, ?string $reason): void { $invoice = $visit->invoice; if (($visit->payer_type?->value ?? $visit->payer_type) === 'cash' && $invoice && $invoice->balance_amount > 0 && ! $actor->can('observation.override-payment')) throw ValidationException::withMessages(['payment'=>'Malipo hayajakamilika.']); if (($visit->payer_type?->value ?? $visit->payer_type) === 'cash' && $invoice && $invoice->balance_amount > 0 && $actor->can('observation.override-payment') && blank($reason)) throw ValidationException::withMessages(['override_reason'=>'Sababu ya override inahitajika.']); if ($reason) ActivityLog::query()->create(['user_id'=>$actor->id,'event'=>'payment_override_used','subject_type'=>$visit::class,'subject_id'=>$visit->id]); }
    public function createInitialMovement(ObservationAdmission $admission, $actor): void { VisitMovement::query()->create(['facility_id'=>$admission->facility_id,'visit_id'=>$admission->visit_id,'patient_id'=>$admission->patient_id,'from_department_id'=>$admission->visit->current_department_id,'to_department_id'=>$admission->visit->current_department_id,'movement_type'=>'observation_admission','status'=>'completed','reason'=>$admission->reason_for_admission,'moved_by'=>$actor->id,'moved_at'=>now()]); }
    public function updateStatus(ObservationAdmission $a, string $status, $actor): ObservationAdmission { $a->update(['status'=>$status,'updated_by'=>$actor->id]); return $a->refresh(); }
    public function cancelAdmission(ObservationAdmission $a, $actor, string $reason): ObservationAdmission { if (! $a->isActive()) throw ValidationException::withMessages(['admission'=>'Admission haiwezi kufutwa.']); $a->update(['status'=>ObservationAdmissionStatus::Cancelled,'notes'=>trim(($a->notes ? $a->notes."\n" : '').'Cancelled: '.$reason),'updated_by'=>$actor->id]); ActivityLog::query()->create(['user_id'=>$actor->id,'event'=>'observation_cancelled','subject_type'=>$a::class,'subject_id'=>$a->id]); return $a->refresh(); }
}
