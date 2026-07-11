<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ObservationAdmissionNumberService
{
    public function next(int $facilityId): string
    {
        return DB::transaction(function () use ($facilityId): string {
            $year = (int) now()->format('Y');
            $row = DB::table('observation_admission_number_sequences')->where('facility_id', $facilityId)->where('year', $year)->lockForUpdate()->first();
            if (! $row) {
                DB::table('observation_admission_number_sequences')->insert(['facility_id' => $facilityId, 'year' => $year, 'next_number' => 2, 'created_at' => now(), 'updated_at' => now()]);
                $number = 1;
            } else {
                $number = (int) $row->next_number;
                DB::table('observation_admission_number_sequences')->where('id', $row->id)->update(['next_number' => $number + 1, 'updated_at' => now()]);
            }
            return 'OBS-'.$year.'-'.str_pad((string) $number, 6, '0', STR_PAD_LEFT);
        });
    }
}
