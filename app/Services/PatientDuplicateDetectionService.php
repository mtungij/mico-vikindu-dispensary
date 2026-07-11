<?php

namespace App\Services;

use App\Models\Patient;

class PatientDuplicateDetectionService
{
    public function detect(array $data): array
    {
        $facilityId = currentFacility()?->id;
        $exact = Patient::query()->where('facility_id', $facilityId)
            ->where(function ($q) use ($data): void {
                $q->when($data['nida_number'] ?? null, fn ($q, $v) => $q->orWhere('nida_number', $v))
                  ->when($data['passport_number'] ?? null, fn ($q, $v) => $q->orWhere('passport_number', $v))
                  ->when($data['primary_phone'] ?? null, fn ($q, $v) => $q->orWhere('primary_phone', $v));
            })->get();

        $possible = Patient::query()->where('facility_id', $facilityId)
            ->where('first_name', $data['first_name'] ?? '')
            ->where('last_name', $data['last_name'] ?? '')
            ->when($data['date_of_birth'] ?? null, fn ($q, $v) => $q->whereDate('date_of_birth', $v))
            ->get();

        return ['exact' => $exact, 'possible' => $possible, 'status' => $exact->isNotEmpty() ? 'exact' : ($possible->isNotEmpty() ? 'possible' : 'none')];
    }
}
