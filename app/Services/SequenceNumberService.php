<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class SequenceNumberService
{
    public function next(string $table, int $facilityId, string $prefix, int $padding = 6, bool $includeYear = true): string
    {
        $year = $includeYear ? now()->year : null;

        return DB::transaction(function () use ($table, $facilityId, $prefix, $padding, $includeYear, $year): string {
            $sequence = DB::table($table)->where('facility_id', $facilityId)->where('year', $year)->lockForUpdate()->first();
            $next = $sequence ? ((int) $sequence->last_number + 1) : 1;

            if ($sequence) {
                DB::table($table)->where('id', $sequence->id)->update(['last_number' => $next, 'updated_at' => now()]);
            } else {
                DB::table($table)->insert(['facility_id' => $facilityId, 'year' => $year, 'last_number' => $next, 'created_at' => now(), 'updated_at' => now()]);
            }

            return implode('-', array_filter([str($prefix)->upper()->toString(), $includeYear ? (string) $year : null, str_pad((string) $next, $padding, '0', STR_PAD_LEFT)]));
        });
    }
}
