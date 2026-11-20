<?php

namespace App\Services;

use App\Enums\MedicationAdministrationStatus;
use App\Models\ActivityLog;
use App\Models\MedicationAdministration;
use App\Models\ObservationAdmission;
use App\Models\PrescriptionItem;
use App\Models\Visit;
use App\Support\MedicationDirections;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class MedicationAdministrationService
{
    public function recordRchInjection(Visit $visit, PrescriptionItem $item, array $data, $actor): MedicationAdministration
    {
        Gate::forUser($actor)->authorize('rch.administer-medication');

        return DB::transaction(function () use ($visit, $item, $data, $actor): MedicationAdministration {
            $item = PrescriptionItem::query()->lockForUpdate()->findOrFail($item->id);
            $prescription = $item->prescription;
            abort_unless($actor->belongsToCurrentFacility() && $visit->facility_id === currentFacility()?->id
                && $visit->destinationDepartment?->code === 'RCH'
                && $prescription->facility_id === $visit->facility_id
                && $prescription->visit_id === $visit->id
                && $prescription->patient_id === $visit->patient_id, 404);
            $route = MedicationDirections::normalizeRoute($item->route);
            if (! in_array($route, ['Intramuscular', 'Intravenous', 'Subcutaneous'], true) || (float) $item->dispensed_quantity <= 0) {
                throw ValidationException::withMessages(['injection' => 'Select a dispensed injectable medicine.']);
            }

            $records = MedicationAdministration::query()->forCurrentFacility()->where('visit_id', $visit->id)
                ->where('prescription_item_id', $item->id)->whereNull('observation_admission_id')
                ->where('administration_status', MedicationAdministrationStatus::Administered);
            $existing = (clone $records)->where('scheduled_at', $data['administered_at'])->first();
            if ($existing) {
                return $existing;
            }
            $available = bcsub((string) $item->dispensed_quantity, ($records->sum('quantity_administered') ?: '0'), 3);
            if (bccomp((string) $data['quantity'], $available, 3) > 0) {
                throw ValidationException::withMessages(['injection' => 'The quantity exceeds what was dispensed and remains available to administer.']);
            }

            $record = MedicationAdministration::query()->create([
                'facility_id' => $visit->facility_id,
                'patient_id' => $visit->patient_id,
                'visit_id' => $visit->id,
                'prescription_item_id' => $item->id,
                'medicine_id' => $item->substitution_medicine_id ?? $item->medicine_id,
                'medicine_name_snapshot' => $item->substitutionMedicine?->name ?? $item->medication_name,
                'dose' => $data['dose'],
                'quantity_administered' => $data['quantity'],
                'route' => $route,
                'frequency' => $item->frequency,
                'scheduled_at' => $data['administered_at'],
                'administration_status' => MedicationAdministrationStatus::Scheduled,
                'created_by' => $actor->id,
            ]);
            $record = $this->administer($record, $actor, $data['notes']);
            $record->update(['administered_at' => $data['administered_at']]);

            return $record;
        });
    }

    public function schedule(ObservationAdmission $a, array $data, $actor): MedicationAdministration
    {
        return MedicationAdministration::query()->create(['facility_id' => $a->facility_id, 'observation_admission_id' => $a->id, 'patient_id' => $a->patient_id, 'visit_id' => $a->visit_id, 'observation_order_id' => $data['observation_order_id'] ?? null, 'prescription_item_id' => $data['prescription_item_id'] ?? null, 'medicine_id' => $data['medicine_id'] ?? null, 'medicine_name_snapshot' => $data['medicine_name_snapshot'], 'dose' => $data['dose'], 'route' => $data['route'], 'frequency' => $data['frequency'] ?? null, 'scheduled_at' => $data['scheduled_at'] ?? now(), 'administration_status' => MedicationAdministrationStatus::Scheduled, 'created_by' => $actor->id]);
    }

    public function administer(MedicationAdministration $m, $actor, ?string $notes = null): MedicationAdministration
    {
        if ($m->administration_status === MedicationAdministrationStatus::Administered) {
            throw ValidationException::withMessages(['medication' => 'Dose tayari imetolewa.']);
        } $m->update(['administration_status' => MedicationAdministrationStatus::Administered, 'administered_at' => now(), 'administered_by' => $actor->id, 'notes' => $notes, 'updated_by' => $actor->id]);
        ActivityLog::query()->create(['user_id' => $actor->id, 'event' => 'medication_administered', 'subject_type' => $m::class, 'subject_id' => $m->id]);

        return $m->refresh();
    }

    public function omit(MedicationAdministration $m, $actor, string $reason): MedicationAdministration
    {
        if (blank($reason)) {
            throw ValidationException::withMessages(['omission_reason' => 'Sababu inahitajika.']);
        } $m->update(['administration_status' => MedicationAdministrationStatus::Omitted, 'omission_reason' => $reason, 'updated_by' => $actor->id]);
        ActivityLog::query()->create(['user_id' => $actor->id, 'event' => 'medication_omitted', 'subject_type' => $m::class, 'subject_id' => $m->id]);

        return $m->refresh();
    }

    public function refuse(MedicationAdministration $m, $actor, string $reason): MedicationAdministration
    {
        if (blank($reason)) {
            throw ValidationException::withMessages(['refusal_reason' => 'Sababu inahitajika.']);
        } $m->update(['administration_status' => MedicationAdministrationStatus::Refused, 'refusal_reason' => $reason, 'updated_by' => $actor->id]);
        ActivityLog::query()->create(['user_id' => $actor->id, 'event' => 'medication_refused', 'subject_type' => $m::class, 'subject_id' => $m->id]);

        return $m->refresh();
    }
}
