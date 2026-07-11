<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\DentalEncounter;
use App\Models\DentalProcedure;
use App\Models\Service;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DentalProcedureService
{
    public function __construct(private readonly DentalEncounterNumberService $numbers, private readonly DentalToothNumberingService $numbering, private readonly DentalBillingService $billing, private readonly DentalOdontogramService $odontogram) {}
    public function validateProcedure(DentalEncounter $encounter, array $data): void
    {
        if ($encounter->isCompleted()) throw ValidationException::withMessages(['encounter'=>'Encounter imekamilika.']);
        if (in_array($data['procedure_type'] ?? '', ['oral_surgery','restorative','endodontic'], true) && blank($data['tooth_number'] ?? null)) throw ValidationException::withMessages(['tooth_number'=>'Jino linahitajika kwa procedure hii.']);
        if (filled($data['tooth_number'] ?? null)) $this->numbering->validateToothNumber($data['tooth_number'], 'mixed');
        foreach (($data['surfaces'] ?? []) as $surface) $this->numbering->validateSurface($data['tooth_number'], $surface);
    }
    public function validateConsent(DentalEncounter $encounter, string $type): void
    {
        if (in_array($type, ['oral_surgery','endodontic'], true) && ! $encounter->consents()->where('consent_given', true)->exists()) throw ValidationException::withMessages(['consent'=>'Ridhaa inahitajika kwa procedure hii.']);
    }
    public function validatePayment(): void {}
    public function createProcedure(DentalEncounter $encounter, Service $service, array $data, $actor): DentalProcedure
    {
        return DB::transaction(function () use ($encounter, $service, $data, $actor) {
            abort_unless($encounter->facility_id === currentFacility()?->id && $service->facility_id === $encounter->facility_id, 404);
            $this->validateProcedure($encounter, $data);
            $procedure = DentalProcedure::query()->create(['facility_id'=>$encounter->facility_id,'dental_encounter_id'=>$encounter->id,'patient_id'=>$encounter->patient_id,'visit_id'=>$encounter->visit_id,'service_id'=>$service->id,'procedure_number'=>$this->numbers->procedure($encounter->facility_id),'procedure_type'=>$data['procedure_type'] ?? 'other','tooth_number'=>$data['tooth_number'] ?? null,'surfaces'=>$data['surfaces'] ?? null,'procedure_name_snapshot'=>$service->name,'indication'=>$data['indication'] ?? null,'diagnosis_snapshot'=>$data['diagnosis_snapshot'] ?? null,'anaesthesia_type'=>$data['anaesthesia_type'] ?? null,'anaesthetic_used'=>$data['anaesthetic_used'] ?? null,'anaesthetic_quantity'=>$data['anaesthetic_quantity'] ?? null,'performed_by'=>$actor->id,'started_at'=>now(),'status'=>'in_progress','findings'=>$data['findings'] ?? null,'technique_notes'=>$data['technique_notes'] ?? null,'post_procedure_instructions'=>$data['post_procedure_instructions'] ?? null,'follow_up_required'=>$data['follow_up_required'] ?? false,'follow_up_date'=>$data['follow_up_date'] ?? null,'created_by'=>$actor->id]);
            $this->billing->addProcedureCharge($procedure, $actor);
            $this->audit($actor, 'dental_procedure_started', $procedure);
            return $procedure->refresh();
        });
    }
    public function recordMaterials(DentalProcedure $procedure, array $materials, $actor): void { foreach ($materials as $m) $procedure->materials()->create([...$m, 'created_by'=>$actor->id]); }
    public function updateOdontogram(DentalProcedure $procedure, $actor): void { $this->odontogram->applyProcedureOutcome($procedure, $actor); }
    public function updateTreatmentPlan(DentalProcedure $procedure): void { $procedure->treatmentPlanItem?->update(['status'=>'completed','completed_at'=>now(),'completed_by'=>$procedure->performed_by]); }
    public function createFollowUp(): void {}
    public function completeProcedure(DentalProcedure $procedure, $actor): DentalProcedure
    {
        return DB::transaction(function () use ($procedure, $actor) {
            $procedure = DentalProcedure::query()->lockForUpdate()->findOrFail($procedure->id);
            if (($procedure->status?->value ?? $procedure->status) === 'completed') throw ValidationException::withMessages(['procedure'=>'Procedure imeshakamilishwa.']);
            $procedure->update(['status'=>'completed','completed_at'=>now(),'updated_by'=>$actor->id]);
            $this->updateOdontogram($procedure, $actor);
            $this->audit($actor, 'dental_procedure_completed', $procedure);
            return $procedure->refresh();
        });
    }
    public function amendProcedure(DentalProcedure $procedure, array $data, string $reason, $actor): DentalProcedure { if (blank($reason)) throw ValidationException::withMessages(['reason'=>'Sababu inahitajika.']); $procedure->update([...$data,'updated_by'=>$actor->id]); $this->audit($actor,'dental_procedure_amended',$procedure); return $procedure->refresh(); }
    private function audit($actor,string $event,DentalProcedure $procedure): void { ActivityLog::query()->create(['user_id'=>$actor->id,'event'=>$event,'subject_type'=>DentalProcedure::class,'subject_id'=>$procedure->id,'new_values'=>[]]); }
}
