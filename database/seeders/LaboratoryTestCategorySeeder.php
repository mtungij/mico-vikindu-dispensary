<?php

namespace Database\Seeders;

use App\Models\Facility;
use App\Models\LaboratoryTestCategory;
use Illuminate\Database\Seeder;

class LaboratoryTestCategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach (Facility::query()->get() as $facility) {
            foreach ([
                ['Haematology', 'HEMA', 'droplet', '#dc2626', 1],
                ['Chemistry', 'CHEM', 'flask-conical', '#2563eb', 2],
                ['Microbiology', 'MICRO', 'microscope', '#059669', 3],
                ['Serology', 'SERO', 'shield-check', '#7c3aed', 4],
                ['Parasitology', 'PARA', 'bug', '#ca8a04', 5],
            ] as [$name, $code, $icon, $color, $order]) {
                LaboratoryTestCategory::query()->updateOrCreate(
                    ['facility_id' => $facility->id, 'code' => $code],
                    ['name' => $name, 'icon' => $icon, 'color' => $color, 'sort_order' => $order, 'is_active' => true],
                );
            }
        }
    }
}
