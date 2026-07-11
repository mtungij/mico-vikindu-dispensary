<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class DentalToothNumberingService
{
    public function getAdultTeeth(): array { return config('dental.adult_teeth'); }
    public function getPrimaryTeeth(): array { return config('dental.primary_teeth'); }
    public function getMixedDentition(): array { return array_values(array_unique([...$this->getAdultTeeth(), ...$this->getPrimaryTeeth()])); }
    public function convertNumber(string $number, string $from = 'fdi', string $to = 'fdi'): string { return $number; }
    public function getToothLabel(string $number): string { return 'Jino '.$number; }
    public function validateToothNumber(string $number, string $dentition = 'mixed'): void
    {
        $pool = match ($dentition) { 'permanent' => $this->getAdultTeeth(), 'primary' => $this->getPrimaryTeeth(), default => $this->getMixedDentition() };
        if (! in_array($number, $pool, true)) {
            throw ValidationException::withMessages(['tooth_number' => 'Namba ya jino si sahihi kwa dentition iliyochaguliwa.']);
        }
    }
    public function surfacesFor(string $number): array
    {
        return in_array($number, config('dental.posterior_teeth'), true) ? array_keys(config('dental.surfaces.posterior')) : array_keys(config('dental.surfaces.anterior'));
    }
    public function validateSurface(string $number, ?string $surface): void
    {
        if ($surface && ! in_array($surface, $this->surfacesFor($number), true)) {
            throw ValidationException::withMessages(['surface' => 'Surface haikubaliki kwa jino hili.']);
        }
    }
    public function getAdjacentTeeth(string $number): array
    {
        $all = $this->getMixedDentition();
        $index = array_search($number, $all, true);
        return $index === false ? [] : array_values(array_filter([$all[$index - 1] ?? null, $all[$index + 1] ?? null]));
    }
}
