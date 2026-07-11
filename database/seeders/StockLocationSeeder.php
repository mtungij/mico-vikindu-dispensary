<?php

namespace Database\Seeders;

use App\Models\Facility;
use App\Models\StockLocation;
use Illuminate\Database\Seeder;

class StockLocationSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Facility::query()->get() as $facility) {
            foreach ([['Main Pharmacy', 'MAIN', true, true], ['Pharmacy Store', 'STORE', false, true], ['Dispensing Counter', 'DSP', true, false]] as [$name, $code, $dispensing, $receiving]) {
                StockLocation::query()->updateOrCreate(['facility_id' => $facility->id, 'code' => $code], ['name' => $name, 'location_type' => 'pharmacy', 'is_dispensing_location' => $dispensing, 'is_receiving_location' => $receiving, 'allows_transfers' => true, 'is_active' => true]);
            }
        }
    }
}
