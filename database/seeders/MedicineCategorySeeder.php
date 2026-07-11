<?php

namespace Database\Seeders;

use App\Models\Facility;
use App\Models\MedicineCategory;
use Illuminate\Database\Seeder;

class MedicineCategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach (Facility::query()->get() as $facility) {
            foreach ([['Analgesics', 'ANAL'], ['Antibiotics', 'ANTI'], ['Antimalarials', 'MALA'], ['Antihistamines', 'HIST'], ['Supplements', 'SUPP']] as [$name, $code]) {
                MedicineCategory::query()->updateOrCreate(['facility_id' => $facility->id, 'code' => $code], ['name' => $name, 'is_active' => true]);
            }
        }
    }
}
