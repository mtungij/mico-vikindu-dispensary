<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\ImmunizationAdministration;
use App\Models\ImmunizationScheduleItem;
use App\Models\MedicineBatch;
use App\Models\RchChild;
use App\Models\Vaccine;
use App\Models\VaccineBatchDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ImmunizationAdministrationService
{
    public function __construct(private readonly ImmunizationScheduleService $schedules) {}

    public function administer(RchChild $child, Vaccine $vaccine, array $data, $actor): ImmunizationAdministration
    {
        return DB::transaction(function () use ($child, $vaccine, $data, $actor): ImmunizationAdministration {
            $scheduleItem = ! empty($data['immunization_schedule_item_id']) ? ImmunizationScheduleItem::query()->findOrFail($data['immunization_schedule_item_id']) : null;
            if ($scheduleItem) {
                $this->schedules->validateDoseInterval($child, $scheduleItem);
            }
            $batch = ! empty($data['medicine_batch_id']) ? MedicineBatch::query()->lockForUpdate()->findOrFail($data['medicine_batch_id']) : null;
            if ($batch) {
                $this->validateBatch($batch, $vaccine);
                $batch->decrement('available_quantity', (float) ($data['dose_quantity'] ?? 1));
            }
            if (($data['status'] ?? 'administered') !== 'administered' && blank($data['reason_not_given'] ?? null)) {
                throw ValidationException::withMessages(['reason_not_given' => 'Sababu inahitajika kwa chanjo ambayo haijatolewa.']);
            }

            $record = ImmunizationAdministration::query()->create([
                'facility_id' => $child->facility_id,
                'patient_id' => $child->child_patient_id,
                'rch_child_id' => $child->id,
                'pregnancy_id' => $data['pregnancy_id'] ?? null,
                'visit_id' => $data['visit_id'] ?? null,
                'rch_encounter_id' => $data['rch_encounter_id'] ?? null,
                'immunization_schedule_item_id' => $scheduleItem?->id,
                'vaccine_id' => $vaccine->id,
                'dose_number' => $data['dose_number'] ?? $scheduleItem?->dose_number,
                'dose_name_snapshot' => $data['dose_name_snapshot'] ?? $scheduleItem?->dose_name ?? $vaccine->name,
                'administration_date' => $data['administration_date'] ?? today(),
                'age_at_administration_days' => $this->schedules->calculateNextDueDate($child, $scheduleItem ?? new ImmunizationScheduleItem(['recommended_age_days' => 0]))->diffInDays($data['administration_date'] ?? today()),
                'medicine_batch_id' => $batch?->id,
                'batch_number_snapshot' => $batch?->batch_number,
                'expiry_date_snapshot' => $batch?->expiry_date,
                'dose_quantity' => $data['dose_quantity'] ?? $vaccine->dosage,
                'dose_unit' => $data['dose_unit'] ?? $vaccine->dosage_unit,
                'route_snapshot' => $data['route_snapshot'] ?? $scheduleItem?->route_snapshot,
                'administration_site' => $data['administration_site'] ?? null,
                'administered_by' => $actor->id,
                'status' => $data['status'] ?? 'administered',
                'reason_not_given' => $data['reason_not_given'] ?? null,
                'adverse_event' => $data['adverse_event'] ?? null,
                'next_due_date' => $data['next_due_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'invoice_item_id' => $data['invoice_item_id'] ?? null,
            ]);
            ActivityLog::query()->create(['user_id' => $actor->id, 'event' => 'immunization_recorded', 'subject_type' => ImmunizationAdministration::class, 'subject_id' => $record->id]);
            return $record;
        });
    }

    private function validateBatch(MedicineBatch $batch, Vaccine $vaccine): void
    {
        if ($batch->expiry_date && $batch->expiry_date->isPast()) {
            throw ValidationException::withMessages(['medicine_batch_id' => 'Batch ime-expire.']);
        }
        $detail = VaccineBatchDetail::query()->where('medicine_batch_id', $batch->id)->first();
        if ($detail && in_array($detail->cold_chain_status, ['quarantined', 'discarded', 'excursion_confirmed'], true)) {
            throw ValidationException::withMessages(['medicine_batch_id' => 'Batch haifai kutumika kwa cold chain status yake.']);
        }
        if ($vaccine->medicine_id && (int) $batch->medicine_id !== (int) $vaccine->medicine_id) {
            throw ValidationException::withMessages(['medicine_batch_id' => 'Batch haiendani na vaccine iliyochaguliwa.']);
        }
        if ((float) $batch->available_quantity <= 0) {
            throw ValidationException::withMessages(['medicine_batch_id' => 'Stock haitoshi.']);
        }
    }
}
