<?php

namespace App\Services;

use App\Models\Department;
use Illuminate\Support\Facades\DB;

class QueueNumberService
{
    public function next(int $facilityId, Department $department): string
    {
        $date = today()->toDateString();

        return DB::transaction(function () use ($facilityId, $department, $date): string {
            $sequence = DB::table('queue_number_sequences')->where('facility_id', $facilityId)->where('department_id', $department->id)->where('queue_date', $date)->lockForUpdate()->first();
            $next = $sequence ? ((int) $sequence->last_number + 1) : 1;
            if ($sequence) {
                DB::table('queue_number_sequences')->where('id', $sequence->id)->update(['last_number' => $next, 'updated_at' => now()]);
            } else {
                DB::table('queue_number_sequences')->insert(['facility_id' => $facilityId, 'department_id' => $department->id, 'queue_date' => $date, 'last_number' => $next, 'created_at' => now(), 'updated_at' => now()]);
            }

            return str($department->code)->upper()->substr(0, 3)->append('-'.str_pad((string) $next, 3, '0', STR_PAD_LEFT))->toString();
        });
    }
}
