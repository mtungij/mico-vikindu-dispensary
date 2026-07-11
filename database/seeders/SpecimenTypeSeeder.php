<?php

namespace Database\Seeders;

use App\Models\Facility;
use App\Models\SpecimenType;
use Illuminate\Database\Seeder;

class SpecimenTypeSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Facility::query()->get() as $facility) {
            foreach ([
                ['Whole Blood', 'WB', 'EDTA Tube', '2', 'mL', '2-8C'],
                ['Serum', 'SER', 'Plain Tube', '2', 'mL', '2-8C'],
                ['Urine', 'URI', 'Sterile Container', '10', 'mL', 'Room Temperature'],
                ['Stool', 'STL', 'Stool Container', '5', 'g', 'Room Temperature'],
                ['Swab', 'SWB', 'Sterile Swab', null, null, 'Room Temperature'],
            ] as [$name, $code, $container, $volume, $unit, $temperature]) {
                SpecimenType::query()->updateOrCreate(
                    ['facility_id' => $facility->id, 'code' => $code],
                    [
                        'name' => $name,
                        'container_type' => $container,
                        'minimum_volume' => $volume,
                        'volume_unit' => $unit,
                        'storage_temperature' => $temperature,
                        'collection_instructions' => 'Label specimen with patient name, visit number and collection time.',
                        'rejection_criteria' => 'Unlabelled, leaking, insufficient or wrong container specimen.',
                        'is_active' => true,
                    ],
                );
            }
        }
    }
}
