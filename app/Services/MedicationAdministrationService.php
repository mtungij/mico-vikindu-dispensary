<?php

namespace App\Services;

use App\Enums\MedicationAdministrationStatus;
use App\Models\ActivityLog;
use App\Models\MedicationAdministration;
use App\Models\ObservationAdmission;
use Illuminate\Validation\ValidationException;

class MedicationAdministrationService
{
    public function schedule(ObservationAdmission $a, array $data, $actor): MedicationAdministration { return MedicationAdministration::query()->create(['facility_id'=>$a->facility_id,'observation_admission_id'=>$a->id,'patient_id'=>$a->patient_id,'visit_id'=>$a->visit_id,'observation_order_id'=>$data['observation_order_id'] ?? null,'prescription_item_id'=>$data['prescription_item_id'] ?? null,'medicine_id'=>$data['medicine_id'] ?? null,'medicine_name_snapshot'=>$data['medicine_name_snapshot'],'dose'=>$data['dose'],'route'=>$data['route'],'frequency'=>$data['frequency'] ?? null,'scheduled_at'=>$data['scheduled_at'] ?? now(),'administration_status'=>MedicationAdministrationStatus::Scheduled,'created_by'=>$actor->id]); }
    public function administer(MedicationAdministration $m, $actor, ?string $notes = null): MedicationAdministration { if ($m->administration_status === MedicationAdministrationStatus::Administered) throw ValidationException::withMessages(['medication'=>'Dose tayari imetolewa.']); $m->update(['administration_status'=>MedicationAdministrationStatus::Administered,'administered_at'=>now(),'administered_by'=>$actor->id,'notes'=>$notes,'updated_by'=>$actor->id]); ActivityLog::query()->create(['user_id'=>$actor->id,'event'=>'medication_administered','subject_type'=>$m::class,'subject_id'=>$m->id]); return $m->refresh(); }
    public function omit(MedicationAdministration $m, $actor, string $reason): MedicationAdministration { if (blank($reason)) throw ValidationException::withMessages(['omission_reason'=>'Sababu inahitajika.']); $m->update(['administration_status'=>MedicationAdministrationStatus::Omitted,'omission_reason'=>$reason,'updated_by'=>$actor->id]); ActivityLog::query()->create(['user_id'=>$actor->id,'event'=>'medication_omitted','subject_type'=>$m::class,'subject_id'=>$m->id]); return $m->refresh(); }
    public function refuse(MedicationAdministration $m, $actor, string $reason): MedicationAdministration { if (blank($reason)) throw ValidationException::withMessages(['refusal_reason'=>'Sababu inahitajika.']); $m->update(['administration_status'=>MedicationAdministrationStatus::Refused,'refusal_reason'=>$reason,'updated_by'=>$actor->id]); ActivityLog::query()->create(['user_id'=>$actor->id,'event'=>'medication_refused','subject_type'=>$m::class,'subject_id'=>$m->id]); return $m->refresh(); }
}
