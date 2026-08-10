<?php

namespace App\Services;

use App\Models\Patient;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class PatientDuplicateDetectionService
{
    public function __construct(private readonly PhoneNumberService $phones) {}

    /**
     * @return array{status: string, exact: Collection, probable: Collection, weak: Collection, possible: Collection, matches: Collection, matched_patient_ids: array<int, int>, reasons: array<int, array<int, string>>}
     */
    public function detect(array $data, array $payerData = []): array
    {
        $facilityId = currentFacility()?->id;
        $phone = filled($data['primary_phone'] ?? null) && $this->phones->isValid($data['primary_phone'])
            ? $this->phones->normalize($data['primary_phone'])
            : null;
        $patientNumber = $this->normalized($data['patient_number'] ?? null);
        $nida = $this->normalized($data['nida_number'] ?? null);
        $passport = $this->normalized($data['passport_number'] ?? null);
        $membership = $this->normalized($payerData['membership_number'] ?? $data['membership_number'] ?? null);
        $firstName = $this->normalized($data['first_name'] ?? null);
        $lastName = $this->normalized($data['last_name'] ?? null);

        if (! $facilityId || ! collect([$patientNumber, $nida, $passport, $phone, $membership, $firstName, $lastName])->filter()->count()) {
            return $this->emptyResult();
        }

        $candidates = Patient::query()
            ->forCurrentFacility()
            ->with(['payerProfiles', 'primaryPayerProfile', 'latestVisit', 'activeVisit'])
            ->where(function ($query) use ($patientNumber, $nida, $passport, $phone, $membership, $firstName, $lastName): void {
                $hasCondition = false;
                foreach ([
                    'patient_number' => $patientNumber,
                    'nida_number' => $nida,
                    'passport_number' => $passport,
                ] as $field => $value) {
                    if ($value) {
                        $query->{$hasCondition ? 'orWhereRaw' : 'whereRaw'}('LOWER('.$field.') = ?', [$value]);
                        $hasCondition = true;
                    }
                }
                if ($phone) {
                    $query->{$hasCondition ? 'orWhere' : 'where'}(fn ($person) => $person
                        ->where('primary_phone', $phone)
                        ->orWhere('primary_phone', 'like', '%'.substr($phone, -9)));
                    $hasCondition = true;
                }
                if ($membership) {
                    $method = $hasCondition ? 'orWhereHas' : 'whereHas';
                    $query->{$method}('payerProfiles', fn ($payer) => $payer->whereRaw('LOWER(membership_number) = ?', [$membership]));
                    $hasCondition = true;
                }
                foreach ([$firstName, $lastName] as $name) {
                    if ($name) {
                        $prefix = mb_substr($name, 0, min(3, mb_strlen($name))).'%';
                        $query->{$hasCondition ? 'orWhere' : 'where'}(fn ($person) => $person
                            ->whereRaw('LOWER(first_name) LIKE ?', [$prefix])
                            ->orWhereRaw('LOWER(last_name) LIKE ?', [$prefix]));
                        $hasCondition = true;
                    }
                }
            })
            ->limit(100)
            ->get();

        $matches = $candidates->map(function (Patient $patient) use ($patientNumber, $nida, $passport, $phone, $membership, $firstName, $lastName, $data): ?array {
            $strong = [];
            $context = [];
            $candidateFirst = $this->normalized($patient->first_name);
            $candidateLast = $this->normalized($patient->last_name);
            $fullNameMatches = $firstName && $lastName && $firstName === $candidateFirst && $lastName === $candidateLast;
            $nameIsSimilar = $firstName && $lastName
                && $this->similar($firstName, $candidateFirst)
                && $this->similar($lastName, $candidateLast);
            $dobMatches = filled($data['date_of_birth'] ?? null)
                && $patient->date_of_birth?->toDateString() === (string) $data['date_of_birth'];
            $ageMatches = filled($data['age_years'] ?? null) && $patient->age_years !== null
                && abs((int) $patient->age_years - (int) $data['age_years']) <= 1;
            $genderMatches = filled($data['gender'] ?? null)
                && ($patient->gender?->value ?? $patient->gender) === $data['gender'];

            if ($patientNumber && $this->normalized($patient->patient_number) === $patientNumber) {
                $strong[] = 'exact_patient_number';
            }
            if ($nida && $this->normalized($patient->nida_number) === $nida) {
                $strong[] = 'exact_nida';
            }
            if ($passport && $this->normalized($patient->passport_number) === $passport) {
                $strong[] = 'exact_passport';
            }
            if ($membership && $patient->payerProfiles->contains(fn ($profile) => $this->normalized($profile->membership_number) === $membership)) {
                $strong[] = 'exact_insurance_membership';
            }

            $candidatePhone = $this->phones->isValid($patient->primary_phone) ? $this->phones->normalize($patient->primary_phone) : null;
            $phoneMatches = $phone && $candidatePhone === $phone;
            if ($phoneMatches && ($fullNameMatches || $dobMatches)) {
                $strong[] = 'exact_phone_with_identity';
            } elseif ($phoneMatches) {
                $context[] = 'exact_phone';
            }

            if ($strong !== []) {
                return $this->match($patient, 'exact', $strong);
            }
            if ($fullNameMatches && $dobMatches) {
                $context[] = 'name_and_dob';
            } elseif ($nameIsSimilar && $dobMatches) {
                $context[] = 'similar_name_and_dob';
            } elseif (($fullNameMatches || $nameIsSimilar) && $genderMatches && $ageMatches) {
                $context[] = 'name_sex_and_close_age';
            } elseif ($fullNameMatches || $nameIsSimilar) {
                $context[] = $fullNameMatches ? 'exact_name_only' : 'similar_name_only';
            }

            if ($context === []) {
                return null;
            }
            $severity = collect($context)->contains(fn (string $reason) => ! in_array($reason, ['exact_name_only', 'similar_name_only'], true)) ? 'probable' : 'weak';

            return $this->match($patient, $severity, $context);
        })->filter()->sortBy(fn (array $match) => ['exact' => 0, 'probable' => 1, 'weak' => 2][$match['severity']])->values();

        $exact = new EloquentCollection($matches->where('severity', 'exact')->pluck('patient')->all());
        $probable = new EloquentCollection($matches->where('severity', 'probable')->pluck('patient')->all());
        $weak = new EloquentCollection($matches->where('severity', 'weak')->pluck('patient')->all());
        $status = $exact->isNotEmpty() ? 'exact' : ($probable->isNotEmpty() ? 'probable' : ($weak->isNotEmpty() ? 'weak' : 'none'));

        return [
            'status' => $status,
            'exact' => $exact,
            'probable' => $probable,
            'weak' => $weak,
            'possible' => $probable->merge($weak),
            'matches' => $matches,
            'matched_patient_ids' => $matches->pluck('patient.id')->unique()->values()->all(),
            'reasons' => $matches->mapWithKeys(fn (array $match) => [$match['patient']->id => $match['reason_codes']])->all(),
        ];
    }

    private function match(Patient $patient, string $severity, array $reasonCodes): array
    {
        $labels = [
            'exact_patient_number' => 'Exact patient number match',
            'exact_nida' => 'Exact NIDA match',
            'exact_passport' => 'Exact passport match',
            'exact_insurance_membership' => 'Exact insurance membership match',
            'exact_phone_with_identity' => 'Exact phone and identity match',
            'exact_phone' => 'Exact phone match',
            'name_and_dob' => 'Name + DOB match',
            'similar_name_and_dob' => 'Similar name + DOB match',
            'name_sex_and_close_age' => 'Name, sex + close age match',
            'exact_name_only' => 'Exact name only',
            'similar_name_only' => 'Similar name',
        ];

        return [
            'patient' => $patient,
            'severity' => $severity,
            'reason_codes' => array_values(array_unique($reasonCodes)),
            'reasons' => collect($reasonCodes)->map(fn (string $reason) => $labels[$reason])->unique()->values()->all(),
        ];
    }

    private function normalized(mixed $value): ?string
    {
        return filled($value) ? mb_strtolower(trim((string) $value)) : null;
    }

    private function similar(string $left, ?string $right): bool
    {
        if (! $right) {
            return false;
        }
        if ($left === $right) {
            return true;
        }

        return levenshtein($left, $right) <= max(1, (int) floor(max(strlen($left), strlen($right)) * 0.2));
    }

    private function emptyResult(): array
    {
        $empty = collect();

        return ['status' => 'none', 'exact' => $empty, 'probable' => $empty, 'weak' => $empty, 'possible' => $empty, 'matches' => $empty, 'matched_patient_ids' => [], 'reasons' => []];
    }
}
