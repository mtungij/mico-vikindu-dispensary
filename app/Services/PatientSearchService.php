<?php

namespace App\Services;

use App\Models\Patient;
use Illuminate\Database\Eloquent\Collection;

class PatientSearchService
{
    public function __construct(private readonly PhoneNumberService $phones) {}

    public function search(string $term, int $limit = 10): Collection
    {
        $term = trim($term);
        if (mb_strlen($term) < 2) return new Collection;

        $normalizedPhone = $this->phones->isValid($term) ? $this->phones->normalize($term) : null;

        return Patient::query()
            ->forCurrentFacility()
            ->with(['primaryPayerProfile', 'latestVisit', 'activeVisit'])
            ->where(function ($query) use ($term, $normalizedPhone): void {
                $like = '%'.$term.'%';
                $query->where('patient_number', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhere('middle_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('primary_phone', 'like', $like)
                    ->orWhere('nida_number', 'like', $like)
                    ->orWhereHas('payerProfiles', fn ($payer) => $payer->where('membership_number', 'like', $like));
                if ($normalizedPhone) $query->orWhere('primary_phone', $normalizedPhone);
                foreach (preg_split('/\s+/', $term, -1, PREG_SPLIT_NO_EMPTY) as $part) {
                    $query->orWhere(fn ($name) => $name->where('first_name', 'like', '%'.$part.'%')->orWhere('last_name', 'like', '%'.$part.'%'));
                }
            })
            ->orderByRaw('CASE WHEN patient_number = ? THEN 0 WHEN primary_phone = ? OR primary_phone = ? THEN 1 ELSE 2 END', [$term, $term, $normalizedPhone ?? $term])
            ->orderByDesc('registered_at')
            ->limit($limit)
            ->get();
    }
}
