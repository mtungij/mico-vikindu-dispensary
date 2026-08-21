<?php

namespace App\Support;

use App\Models\Medicine;

class MedicationDirections
{
    public const FREQUENCIES = [
        'Once daily', 'Twice daily', 'Three times daily', 'Four times daily',
        'Every 4 hours', 'Every 6 hours', 'Every 8 hours', 'Every 12 hours',
        'At night', 'In the morning', 'As needed / PRN', 'Stat / once',
    ];

    public const ROUTES = [
        'Oral', 'Intravenous', 'Intramuscular', 'Subcutaneous', 'Topical',
        'Rectal', 'Vaginal', 'Ophthalmic', 'Otic', 'Nasal', 'Inhalation', 'Sublingual',
    ];

    public const DURATION_UNITS = ['hours', 'days', 'weeks', 'months', 'until_finished', 'single_dose'];

    public static function normalizeFrequency(?string $value): ?string
    {
        $key = str((string) $value)->trim()->lower()->replaceMatches('/\s+/', ' ')->toString();

        return [
            'od' => 'Once daily', 'daily' => 'Once daily', 'once daily' => 'Once daily',
            'bd' => 'Twice daily', 'bid' => 'Twice daily', 'twice daily' => 'Twice daily',
            'tds' => 'Three times daily', 'tid' => 'Three times daily', 'three times daily' => 'Three times daily',
            'qid' => 'Four times daily', 'four times daily' => 'Four times daily',
            'q4h' => 'Every 4 hours', 'every 4 hours' => 'Every 4 hours',
            'q6h' => 'Every 6 hours', 'every 6 hours' => 'Every 6 hours',
            'q8h' => 'Every 8 hours', 'every 8 hours' => 'Every 8 hours',
            'q12h' => 'Every 12 hours', 'every 12 hours' => 'Every 12 hours',
            'nocte' => 'At night', 'at night' => 'At night',
            'mane' => 'In the morning', 'in the morning' => 'In the morning',
            'prn' => 'As needed / PRN', 'as needed' => 'As needed / PRN', 'as needed / prn' => 'As needed / PRN',
            'stat' => 'Stat / once', 'once' => 'Stat / once', 'stat / once' => 'Stat / once',
        ][$key] ?? null;
    }

    public static function normalizeRoute(?string $value): ?string
    {
        $key = str((string) $value)->trim()->lower()->toString();

        return [
            'oral' => 'Oral', 'po' => 'Oral',
            'intravenous' => 'Intravenous', 'iv' => 'Intravenous',
            'intramuscular' => 'Intramuscular', 'im' => 'Intramuscular',
            'subcutaneous' => 'Subcutaneous', 'sc' => 'Subcutaneous', 'sq' => 'Subcutaneous',
            'topical' => 'Topical', 'rectal' => 'Rectal', 'pr' => 'Rectal',
            'vaginal' => 'Vaginal', 'pv' => 'Vaginal', 'ophthalmic' => 'Ophthalmic',
            'otic' => 'Otic', 'nasal' => 'Nasal', 'inhalation' => 'Inhalation',
            'sublingual' => 'Sublingual', 'sl' => 'Sublingual',
        ][$key] ?? null;
    }

    public static function frequencyDosesPerDay(?string $frequency): ?float
    {
        return match (self::normalizeFrequency($frequency)) {
            'Once daily', 'At night', 'In the morning' => 1,
            'Twice daily', 'Every 12 hours' => 2,
            'Three times daily', 'Every 8 hours' => 3,
            'Four times daily', 'Every 6 hours' => 4,
            'Every 4 hours' => 6,
            default => null,
        };
    }

    public static function doseUnits(?string $dose): ?float
    {
        if (! preg_match('/^\s*(\d+(?:\.\d+)?|\d+\s*\/\s*\d+)\s+([\pL][\pL\s.-]*)$/u', (string) $dose, $match)) {
            return null;
        }
        $unit = str($match[2])->trim()->lower()->toString();
        if (! preg_match('/^(tablet|tablets|tab|tabs|capsule|capsules|cap|caps|ml|millilitre|millilitres|vial|vials|ampoule|ampoules|puff|puffs|drop|drops|sachet|sachets|suppository|suppositories|application|applications)$/', $unit)) {
            return null;
        }
        if (str_contains($match[1], '/')) {
            [$numerator, $denominator] = array_map('floatval', preg_split('/\s*\/\s*/', $match[1]));

            return $denominator > 0 ? $numerator / $denominator : null;
        }

        return (float) $match[1];
    }

    public static function calculateQuantity(?string $dose, ?string $frequency, mixed $durationValue, ?string $durationUnit): ?float
    {
        $units = self::doseUnits($dose);
        if ($units !== null && self::normalizeFrequency($frequency) === 'Stat / once') {
            return round($units, 2);
        }
        $perDay = self::frequencyDosesPerDay($frequency);
        $duration = is_numeric($durationValue) ? (float) $durationValue : 0;
        if ($units === null || $perDay === null || $duration <= 0) {
            return null;
        }
        $days = match ($durationUnit) {
            'hours' => $duration / 24,
            'days' => $duration,
            'weeks' => $duration * 7,
            'months' => $duration * 30,
            'single_dose' => 1 / $perDay,
            default => null,
        };
        if ($days === null) {
            return null;
        }
        $quantity = $units * $perDay * $days;

        return $quantity >= 1 && abs($quantity - round($quantity, 2)) < 0.0001 ? round($quantity, 2) : null;
    }

    public static function displayFrequency(?string $value): ?string
    {
        return self::normalizeFrequency($value) ?? $value;
    }

    public static function displayRoute(?string $value): ?string
    {
        return self::normalizeRoute($value) ?? $value;
    }

    /** @return array<string, string> */
    public static function doseOptions(?Medicine $medicine): array
    {
        $form = str($medicine?->dosageForm?->name)->lower()->toString();
        $unit = str($medicine?->dispensingUnit?->name ?: $medicine?->dispensingUnit?->symbol)->lower()->toString();
        if (str_contains($form, 'tablet') || str_contains($unit, 'tablet') || $unit === 'tab') {
            return ['1/2 tablet' => '1/2 tablet', '1 tablet' => '1 tablet', '2 tablets' => '2 tablets'];
        }
        if (str_contains($form, 'capsule') || str_contains($unit, 'capsule') || $unit === 'cap') {
            return ['1 capsule' => '1 capsule', '2 capsules' => '2 capsules'];
        }
        if ($medicine?->dosageForm?->is_liquid || in_array($unit, ['ml', 'millilitre', 'millilitres'], true)) {
            return ['2.5 mL' => '2.5 mL', '5 mL' => '5 mL', '10 mL' => '10 mL'];
        }

        return [];
    }

    public static function quantityUnit(?Medicine $medicine, float $quantity): ?string
    {
        $raw = trim((string) ($medicine?->dispensingUnit?->name ?: $medicine?->dispensingUnit?->symbol));
        if ($raw === '') {
            return null;
        }

        return $quantity === 1.0 ? str($raw)->singular()->toString() : str($raw)->plural()->toString();
    }
}
