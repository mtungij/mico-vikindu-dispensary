<?php

namespace App\Services;

use App\Models\Patient;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class PatientSearchService
{
    public function __construct(private readonly PhoneNumberService $phones) {}

    public function search(string $term, int $limit = 10): EloquentCollection
    {
        return new EloquentCollection($this->searchWithReasons($term, $limit)->pluck('patient')->all());
    }

    /** @return Collection<int, array{patient: Patient, reason: string, rank: int}> */
    public function searchWithReasons(string $term, int $limit = 10): Collection
    {
        $term = trim($term);
        if (mb_strlen($term) < 2) {
            return collect();
        }

        $normalized = mb_strtolower($term);
        $normalizedPhone = $this->phones->isValid($term) ? $this->phones->normalize($term) : null;
        $parts = preg_split('/\s+/', $normalized, -1, PREG_SPLIT_NO_EMPTY);

        return Patient::query()
            ->forCurrentFacility()
            ->with(['payerProfiles', 'primaryPayerProfile', 'latestVisit', 'activeVisit.currentDepartment', 'activeVisit.invoice', 'activeVisit.laboratoryOrders'])
            ->where(function ($query) use ($term, $normalizedPhone, $parts): void {
                $like = '%'.$term.'%';
                $query->where('patient_number', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhere('middle_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('primary_phone', 'like', $like)
                    ->orWhere('nida_number', 'like', $like)
                    ->orWhereHas('payerProfiles', fn ($payer) => $payer->where('membership_number', 'like', $like));
                if ($normalizedPhone) {
                    $query->orWhere('primary_phone', $normalizedPhone)->orWhere('primary_phone', 'like', '%'.substr($normalizedPhone, -9));
                }
                foreach ($parts as $part) {
                    $query->orWhere(fn ($name) => $name
                        ->where('first_name', 'like', '%'.$part.'%')
                        ->orWhere('middle_name', 'like', '%'.$part.'%')
                        ->orWhere('last_name', 'like', '%'.$part.'%'));
                }
            })
            ->limit(50)
            ->get()
            ->map(function (Patient $patient) use ($normalized, $normalizedPhone): array {
                $patientPhone = $this->phones->isValid($patient->primary_phone) ? $this->phones->normalize($patient->primary_phone) : null;
                $fullName = mb_strtolower($patient->fullName());
                [$rank, $reason] = match (true) {
                    mb_strtolower($patient->patient_number) === $normalized => [1, 'Exact patient number match'],
                    filled($patient->nida_number) && mb_strtolower($patient->nida_number) === $normalized => [2, 'Exact NIDA match'],
                    $patient->payerProfiles->contains(fn ($payer) => filled($payer->membership_number) && mb_strtolower($payer->membership_number) === $normalized) => [3, 'Exact insurance membership match'],
                    $normalizedPhone && $patientPhone === $normalizedPhone => [4, 'Exact phone match'],
                    $fullName === $normalized => [5, 'Exact full name match'],
                    default => [7, 'Similar name or identifier'],
                };

                return compact('patient', 'rank', 'reason');
            })
            ->sortBy(fn (array $match) => [$match['rank'], -($match['patient']->registered_at?->timestamp ?? 0)])
            ->take($limit)
            ->values();
    }
}
