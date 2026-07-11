<?php

namespace Database\Seeders;

use App\Models\Facility;
use App\Models\GenericMedicine;
use Illuminate\Database\Seeder;

class GenericMedicineSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Facility::query()->get() as $facility) {
            foreach ([['Paracetamol', 'PAR'], ['Amoxicillin', 'AMX'], ['Artemether Lumefantrine', 'ALU'], ['Cetirizine', 'CET'], ['Ferrous Sulphate', 'FER']] as [$name, $code]) {
                GenericMedicine::query()->updateOrCreate(['facility_id' => $facility->id, 'code' => $code], ['name' => $name, 'is_active' => true]);
            }
        }
    }
}
