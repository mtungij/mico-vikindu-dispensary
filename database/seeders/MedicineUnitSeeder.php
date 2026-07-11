<?php

namespace Database\Seeders;

use App\Models\Facility;
use App\Models\MedicineUnit;
use Illuminate\Database\Seeder;

class MedicineUnitSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Facility::query()->get() as $facility) {
            foreach ([['Tablet', 'tab'], ['Capsule', 'cap'], ['Bottle', 'btl'], ['Vial', 'vial'], ['Tube', 'tube']] as [$name, $symbol]) {
                MedicineUnit::query()->updateOrCreate(['facility_id' => $facility->id, 'symbol' => $symbol], ['name' => $name, 'is_active' => true]);
            }
        }
    }
}
