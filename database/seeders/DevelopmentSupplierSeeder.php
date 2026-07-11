<?php

namespace Database\Seeders;

use App\Models\Facility;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class DevelopmentSupplierSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Facility::query()->get() as $facility) {
            Supplier::query()->updateOrCreate(['facility_id' => $facility->id, 'code' => 'MEDSUP'], ['name' => 'Demo Medical Supplies', 'supplier_type' => 'pharmaceutical_wholesaler', 'contact_person' => 'Sales Desk', 'phone_primary' => '0712000000', 'email' => 'sales@example.test', 'is_active' => true]);
        }
    }
}
