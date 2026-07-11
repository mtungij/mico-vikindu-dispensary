<?php

namespace App\Services;

use App\Models\Patient;
use Illuminate\Support\Facades\DB;

class PatientService
{
    public function __construct(private readonly PatientNumberService $numbers, private readonly PhoneNumberService $phone) {}

    public function createPatient(array $data, $actor): Patient
    {
        return DB::transaction(function () use ($data, $actor): Patient {
            if (blank($data['date_of_birth'] ?? null) && filled($data['age_years'] ?? null)) {
                $data['date_of_birth'] = now()->subYears((int) $data['age_years'])->subMonths((int) ($data['age_months'] ?? 0))->toDateString();
                $data['date_of_birth_is_estimated'] = true;
            }
            if (filled($data['primary_phone'] ?? null)) {
                $data['primary_phone'] = $this->phone->normalize($data['primary_phone']);
            }
            return Patient::query()->create([
                ...$data,
                'facility_id' => currentFacility()?->id,
                'patient_number' => $data['patient_number'] ?? $this->numbers->next(),
                'registered_at' => now(),
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
        });
    }
}
