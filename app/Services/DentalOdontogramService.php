<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\DentalEncounter;
use App\Models\DentalFindingType;
use App\Models\DentalToothFinding;
use App\Models\DentalToothRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DentalOdontogramService
{
    public function __construct(private readonly DentalToothNumberingService $numbering) {}

    public function initializeDentition(DentalEncounter $encounter, string $dentition, $actor): void
    {
        $teeth = match ($dentition) { 'primary' => $this->numbering->getPrimaryTeeth(), 'mixed' => $this->numbering->getMixedDentition(), default => $this->numbering->getAdultTeeth() };
        foreach ($teeth as $tooth) {
            DentalToothRecord::query()->firstOrCreate(['dental_encounter_id'=>$encounter->id,'tooth_number'=>$tooth], ['facility_id'=>$encounter->facility_id,'patient_id'=>$encounter->patient_id,'dentition_type'=>$dentition === 'mixed' ? (in_array($tooth, $this->numbering->getPrimaryTeeth(), true) ? 'primary' : 'permanent') : $dentition,'tooth_status'=>'present','created_by'=>$actor->id]);
        }
        $this->audit($actor, 'odontogram_initialized', $encounter, ['dentition'=>$dentition]);
    }

    public function getToothRecord(DentalEncounter $encounter, string $toothNumber, string $dentition = 'mixed', $actor = null): DentalToothRecord
    {
        $this->numbering->validateToothNumber($toothNumber, $dentition);
        return DentalToothRecord::query()->firstOrCreate(['dental_encounter_id'=>$encounter->id,'tooth_number'=>$toothNumber], ['facility_id'=>$encounter->facility_id,'patient_id'=>$encounter->patient_id,'dentition_type'=>$dentition === 'mixed' ? 'permanent' : $dentition,'tooth_status'=>'present','created_by'=>$actor?->id ?? auth()->id()]);
    }

    public function addFinding(DentalEncounter $encounter, array $data, $actor): DentalToothFinding
    {
        return DB::transaction(function () use ($encounter, $data, $actor) {
            if ($encounter->isCompleted()) throw ValidationException::withMessages(['encounter'=>'Encounter imekamilika.']);
            $record = $this->getToothRecord($encounter, $data['tooth_number'], $data['dentition_type'] ?? 'mixed', $actor);
            $type = DentalFindingType::query()->where(fn($q)=>$q->whereNull('facility_id')->orWhere('facility_id',$encounter->facility_id))->where('is_active', true)->findOrFail($data['finding_type_id']);
            if (($data['surface'] ?? null) && ! $type->applies_to_surface) throw ValidationException::withMessages(['surface'=>'Finding hii haitumii surface.']);
            $this->numbering->validateSurface($record->tooth_number, $data['surface'] ?? null);
            $finding = DentalToothFinding::query()->create(['facility_id'=>$encounter->facility_id,'dental_tooth_record_id'=>$record->id,'dental_encounter_id'=>$encounter->id,'finding_type_id'=>$type->id,'surface'=>$data['surface'] ?? null,'severity'=>$data['severity'] ?? null,'finding_status'=>'active','description'=>$data['description'] ?? null,'diagnosed_by'=>$actor->id,'diagnosed_at'=>now(),'created_by'=>$actor->id]);
            $this->audit($actor, 'dental_finding_added', $encounter, ['tooth_number'=>$record->tooth_number,'finding_type_id'=>$type->id]);
            return $finding;
        });
    }

    public function markFindingError(DentalToothFinding $finding, string $reason, $actor): void
    {
        abort_unless($finding->facility_id === currentFacility()?->id, 404);
        if (blank($reason)) throw ValidationException::withMessages(['reason'=>'Sababu inahitajika.']);
        $finding->update(['finding_status'=>'entered_in_error','description'=>trim(($finding->description ?? '')."\nError: ".$reason),'updated_by'=>$actor->id]);
        $this->audit($actor, 'dental_finding_marked_error', $finding->encounter, ['finding_id'=>$finding->id]);
    }

    public function markToothMissing(DentalEncounter $encounter, string $toothNumber, $actor): DentalToothRecord { return $this->updateToothStatus($encounter, $toothNumber, 'missing', $actor); }
    public function markToothExtracted(DentalEncounter $encounter, string $toothNumber, $actor): DentalToothRecord { return $this->updateToothStatus($encounter, $toothNumber, 'extracted', $actor); }
    public function applyProcedureOutcome($procedure, $actor): void
    {
        if ($procedure->tooth_number && $procedure->procedure_type->value === 'oral_surgery') $this->markToothExtracted($procedure->encounter, $procedure->tooth_number, $actor);
    }
    public function getCurrentChart(DentalEncounter $encounter): array { return $encounter->toothRecords()->with('findings.type')->get()->keyBy('tooth_number')->all(); }
    public function getHistoricalChart($patient): array { return DentalEncounter::query()->where('patient_id', $patient->id)->with('toothRecords.findings.type')->latest()->limit(5)->get()->all(); }

    private function updateToothStatus(DentalEncounter $encounter, string $toothNumber, string $status, $actor): DentalToothRecord
    {
        $record = $this->getToothRecord($encounter, $toothNumber, 'mixed', $actor);
        $record->update(['tooth_status'=>$status,'updated_by'=>$actor->id]);
        return $record->refresh();
    }
    private function audit($actor, string $event, DentalEncounter $encounter, array $values = []): void { ActivityLog::query()->create(['user_id'=>$actor->id,'event'=>$event,'subject_type'=>DentalEncounter::class,'subject_id'=>$encounter->id,'new_values'=>$values,'ip_address'=>request()?->ip(),'user_agent'=>request()?->userAgent()]); }
}
