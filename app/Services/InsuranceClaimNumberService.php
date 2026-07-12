<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class InsuranceClaimNumberService
{
    public function claim(int $facilityId): string
    {
        return $this->next('insurance_claim_number_sequences', 'CLM', $facilityId);
    }

    public function batch(int $facilityId): string
    {
        return $this->next('insurance_claim_batch_number_sequences', 'CLB', $facilityId);
    }

    protected function next(string $table, string $prefix, int $facilityId): string
    {
        $year = (int) now()->format('Y');

        return DB::transaction(function () use ($table, $prefix, $facilityId, $year): string {
            $sequence = DB::table($table)->where('facility_id', $facilityId)->where('year', $year)->lockForUpdate()->first();
            if (! $sequence) {
                DB::table($table)->insert(['facility_id' => $facilityId, 'year' => $year, 'last_number' => 0, 'created_at' => now(), 'updated_at' => now()]);
                $sequence = DB::table($table)->where('facility_id', $facilityId)->where('year', $year)->lockForUpdate()->first();
            }
            $next = ((int) $sequence->last_number) + 1;
            DB::table($table)->where('id', $sequence->id)->update(['last_number' => $next, 'updated_at' => now()]);

            return sprintf('%s-%d-%06d', $prefix, $year, $next);
        });
    }
}
