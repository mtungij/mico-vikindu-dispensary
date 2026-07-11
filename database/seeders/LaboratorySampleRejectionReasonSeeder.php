<?php

namespace Database\Seeders;

use App\Models\Facility;
use App\Models\LaboratorySampleRejectionReason;
use Illuminate\Database\Seeder;

class LaboratorySampleRejectionReasonSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Facility::query()->get() as $facility) {
            foreach ([
                ['UNLBL', 'Unlabelled specimen'],
                ['LEAK', 'Leaking container'],
                ['INSUF', 'Insufficient volume'],
                ['WRONG', 'Wrong specimen/container'],
                ['HEMO', 'Haemolysed specimen'],
                ['DELAY', 'Delayed transport'],
            ] as $index => [$code, $name]) {
                LaboratorySampleRejectionReason::query()->updateOrCreate(
                    ['facility_id' => $facility->id, 'code' => $code],
                    ['name' => $name, 'requires_recollection' => true, 'is_active' => true, 'sort_order' => $index + 1],
                );
            }
        }
    }
}
