<?php

namespace Database\Seeders;

use App\Models\Facility;
use App\Models\MedicineRoute;
use Illuminate\Database\Seeder;

class MedicineRouteSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Facility::query()->get() as $facility) {
            foreach ([['Oral', 'PO'], ['Intravenous', 'IV'], ['Intramuscular', 'IM'], ['Topical', 'TOP']] as [$name, $code]) {
                MedicineRoute::query()->updateOrCreate(['facility_id' => $facility->id, 'code' => $code], ['name' => $name, 'is_active' => true]);
            }
        }
    }
}
