<?php

namespace Database\Seeders;

use App\Models\DosageForm;
use App\Models\Facility;
use Illuminate\Database\Seeder;

class DosageFormSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Facility::query()->get() as $facility) {
            foreach ([['Tablet', 'TAB'], ['Capsule', 'CAP'], ['Syrup', 'SYR'], ['Injection', 'INJ'], ['Cream', 'CRM']] as [$name, $code]) {
                DosageForm::query()->updateOrCreate(['facility_id' => $facility->id, 'code' => $code], ['name' => $name, 'is_active' => true]);
            }
        }
    }
}
