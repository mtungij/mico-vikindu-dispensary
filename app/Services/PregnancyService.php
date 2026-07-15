<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Patient;
use App\Models\Pregnancy;
use App\Models\PregnancyDatingRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PregnancyService
{
    public function __construct(private readonly RchEncounterNumberService $numbers, private readonly PregnancyDatingService $dating) {}

    public function register(Patient $patient, array $data, $actor): Pregnancy
    {
        return DB::transaction(function () use ($patient, $data, $actor): Pregnancy {
            $facilityId = currentFacility()?->id ?? $patient->facility_id;
            $activeExists = Pregnancy::query()->where('facility_id', $facilityId)->where('patient_id', $patient->id)->where('status', 'active')->lockForUpdate()->exists();
            if ($activeExists && empty($data['override_duplicate'])) {
                throw ValidationException::withMessages(['patient_id' => 'Mgonjwa tayari ana active pregnancy.']);
            }
            if ($activeExists && blank($data['override_reason'] ?? null)) {
                throw ValidationException::withMessages(['override_reason' => 'Sababu ya override inahitajika.']);
            }

            $this->dating->validateDates($data['lmp_date'] ?? null, $data['estimated_delivery_date'] ?? null);
            $estimate = $this->dating->determineBestEstimate($data['lmp_date'] ?? null);
            $heightM = ! empty($data['booking_height_cm']) ? ((float) $data['booking_height_cm'] / 100) : null;
            $bmi = $heightM && ! empty($data['booking_weight_kg']) ? round(((float) $data['booking_weight_kg']) / ($heightM * $heightM), 2) : null;

            $pregnancy = Pregnancy::query()->create([
                'facility_id' => $facilityId,
                'patient_id' => $patient->id,
                'pregnancy_number' => $this->numbers->pregnancy($facilityId),
                'status' => 'active',
                'lmp_date' => $data['lmp_date'] ?? null,
                'lmp_is_certain' => $data['lmp_is_certain'] ?? true,
                'estimated_delivery_date' => $data['estimated_delivery_date'] ?? $estimate['edd'],
                'dating_method' => $data['dating_method'] ?? $estimate['method'],
                'gravida' => $data['gravida'] ?? null,
                'para' => $data['para'] ?? null,
                'term_births' => $data['term_births'] ?? null,
                'preterm_births' => $data['preterm_births'] ?? null,
                'abortions' => $data['abortions'] ?? null,
                'living_children' => $data['living_children'] ?? null,
                'multiple_pregnancy' => $data['multiple_pregnancy'] ?? false,
                'number_of_fetuses' => $data['number_of_fetuses'] ?? null,
                'blood_group_snapshot' => $patient->blood_group,
                'rhesus_factor_snapshot' => $patient->rhesus_factor,
                'booking_weight_kg' => $data['booking_weight_kg'] ?? null,
                'booking_height_cm' => $data['booking_height_cm'] ?? null,
                'booking_bmi' => $bmi,
                'risk_level' => 'low',
                'registered_by' => $actor->id,
                'registered_at' => now(),
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);

            if ($pregnancy->estimated_delivery_date) {
                PregnancyDatingRecord::query()->create([
                    'pregnancy_id' => $pregnancy->id,
                    'dating_method' => $pregnancy->dating_method ?? 'lmp',
                    'reference_date' => $pregnancy->lmp_date ?? today(),
                    'calculated_edd' => $pregnancy->estimated_delivery_date,
                    'is_primary' => true,
                    'reason' => 'Initial registration',
                    'recorded_by' => $actor->id,
                    'recorded_at' => now(),
                ]);
            }

            ActivityLog::query()->create(['user_id' => $actor->id, 'event' => 'pregnancy_registered', 'subject_type' => Pregnancy::class, 'subject_id' => $pregnancy->id]);
            return $pregnancy;
        });
    }

    public function amend(Pregnancy $pregnancy, array $data, $actor): Pregnancy
    {
        if ($pregnancy->status !== 'active' && blank($data['amendment_reason'] ?? null)) {
            throw ValidationException::withMessages(['amendment_reason' => 'Sababu ya amendment inahitajika.']);
        }
        $pregnancy->update(array_merge($data, ['updated_by' => $actor->id]));
        ActivityLog::query()->create(['user_id' => $actor->id, 'event' => 'pregnancy_amended', 'subject_type' => Pregnancy::class, 'subject_id' => $pregnancy->id, 'new_values' => ['reason' => $data['amendment_reason'] ?? null]]);
        return $pregnancy->refresh();
    }
}
