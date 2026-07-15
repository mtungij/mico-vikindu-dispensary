<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class AppointmentNumberService
{
    public function next(int $facilityId): string
    {
        $year = (int) now()->format('Y');

        return DB::transaction(function () use ($facilityId, $year): string {
            $sequence = DB::table('appointment_number_sequences')
                ->where('facility_id', $facilityId)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                DB::table('appointment_number_sequences')->insert([
                    'facility_id' => $facilityId,
                    'year' => $year,
                    'last_number' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $sequence = DB::table('appointment_number_sequences')->where('facility_id', $facilityId)->where('year', $year)->lockForUpdate()->first();
            }

            $next = (int) $sequence->last_number + 1;
            DB::table('appointment_number_sequences')->where('id', $sequence->id)->update(['last_number' => $next, 'updated_at' => now()]);

            return sprintf('APT-%d-%06d', $year, $next);
        });
    }
}
