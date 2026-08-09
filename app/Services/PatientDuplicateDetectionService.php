<?php

namespace App\Services;

use App\Models\Patient;

class PatientDuplicateDetectionService
{
    public function __construct(private readonly PhoneNumberService $phones) {}

    public function detect(array $data): array
    {
        $facilityId = currentFacility()?->id;
        if (filled($data['primary_phone'] ?? null) && $this->phones->isValid($data['primary_phone'])) {
            $data['primary_phone'] = $this->phones->normalize($data['primary_phone']);
        }
        $identifiers = collect(['nida_number', 'passport_number', 'primary_phone'])->filter(fn ($field) => filled($data[$field] ?? null));
        $exact = $identifiers->isEmpty() ? collect() : Patient::query()->where('facility_id', $facilityId)
            ->where(function ($q) use ($data, $identifiers): void {
                foreach ($identifiers as $field) $q->orWhere($field, $data[$field]);
            })->get();

        $hasNamePair = filled($data['first_name'] ?? null) && filled($data['last_name'] ?? null);
        $possible = ! $hasNamePair ? collect() : Patient::query()->where('facility_id', $facilityId)
            ->whereRaw('LOWER(first_name) = ?', [mb_strtolower(trim((string) ($data['first_name'] ?? '')))])
            ->whereRaw('LOWER(last_name) = ?', [mb_strtolower(trim((string) ($data['last_name'] ?? '')))])
            ->when($data['date_of_birth'] ?? null, fn ($q, $v) => $q->whereDate('date_of_birth', $v))
            ->get();

        return ['exact' => $exact, 'possible' => $possible, 'status' => $exact->isNotEmpty() ? 'exact' : ($possible->isNotEmpty() ? 'possible' : 'none')];
    }
}
