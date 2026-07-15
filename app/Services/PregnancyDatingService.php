<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class PregnancyDatingService
{
    public function calculateEddFromLmp(string|\DateTimeInterface $lmp): CarbonImmutable
    {
        return CarbonImmutable::parse($lmp)->addDays(280);
    }

    public function calculateGestationalAge(string|\DateTimeInterface $referenceDate, string|\DateTimeInterface|null $asOf = null): array
    {
        $days = max(0, CarbonImmutable::parse($referenceDate)->diffInDays(CarbonImmutable::parse($asOf ?? today())));
        return ['weeks' => intdiv($days, 7), 'days' => $days % 7, 'total_days' => $days];
    }

    public function calculateEddFromUltrasound(string|\DateTimeInterface $scanDate, int $weeks, int $days = 0): CarbonImmutable
    {
        return CarbonImmutable::parse($scanDate)->addDays(280 - (($weeks * 7) + $days));
    }

    public function determineBestEstimate(?string $lmpDate, ?string $ultrasoundDate = null, ?int $ultrasoundWeeks = null, ?int $ultrasoundDays = null): array
    {
        if ($ultrasoundDate && $ultrasoundWeeks !== null) {
            return ['method' => 'ultrasound', 'edd' => $this->calculateEddFromUltrasound($ultrasoundDate, $ultrasoundWeeks, $ultrasoundDays ?? 0)];
        }
        if ($lmpDate) {
            return ['method' => 'lmp', 'edd' => $this->calculateEddFromLmp($lmpDate)];
        }
        return ['method' => 'unknown', 'edd' => null];
    }

    public function validateDates(?string $lmpDate, ?string $edd = null): void
    {
        if ($lmpDate && CarbonImmutable::parse($lmpDate)->isFuture()) {
            throw ValidationException::withMessages(['lmp_date' => 'LMP haiwezi kuwa tarehe ya mbele.']);
        }
        if ($edd && CarbonImmutable::parse($edd)->lessThan(today()->subMonths(10))) {
            throw ValidationException::withMessages(['estimated_delivery_date' => 'EDD si sahihi.']);
        }
    }

    public function formatGestationalAge(int $weeks, int $days = 0): string
    {
        return "{$weeks}w {$days}d";
    }
}
