<?php

namespace App\Services;

use App\Models\FacilitySetting;
use Illuminate\Support\Facades\DB;

class StaffNumberService
{
    public function next(?int $facilityId = null): string
    {
        $facilityId ??= currentFacility()?->id;
        $year = now()->year;
        $includeYear = $this->setting('staff_number_include_year', true);
        $sequenceYear = $includeYear ? $year : null;
        $prefix = $this->setting('staff_number_prefix', 'STF');
        $padding = (int) $this->setting('staff_number_padding', 4);

        return DB::transaction(function () use ($facilityId, $sequenceYear, $prefix, $padding, $includeYear, $year): string {
            $sequence = DB::table('staff_number_sequences')
                ->where('facility_id', $facilityId)
                ->where('year', $sequenceYear)
                ->lockForUpdate()
                ->first();

            if ($sequence === null) {
                DB::table('staff_number_sequences')->insert([
                    'facility_id' => $facilityId,
                    'year' => $sequenceYear,
                    'last_number' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $number = 1;
            } else {
                $number = ((int) $sequence->last_number) + 1;
                DB::table('staff_number_sequences')
                    ->where('id', $sequence->id)
                    ->update(['last_number' => $number, 'updated_at' => now()]);
            }

            $parts = [str($prefix)->upper()->toString()];
            if ($includeYear) {
                $parts[] = (string) $year;
            }
            $parts[] = str_pad((string) $number, $padding, '0', STR_PAD_LEFT);

            return implode('-', $parts);
        });
    }

    private function setting(string $key, mixed $default): mixed
    {
        $facilityId = currentFacility()?->id;

        return FacilitySetting::query()
            ->where('facility_id', $facilityId)
            ->where('key', $key)
            ->value('value') ?? $default;
    }
}
