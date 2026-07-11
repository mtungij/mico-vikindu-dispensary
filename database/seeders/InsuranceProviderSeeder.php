<?php

namespace Database\Seeders;

use App\Models\Facility;
use App\Models\InsuranceProvider;
use Illuminate\Database\Seeder;

class InsuranceProviderSeeder extends Seeder
{
    public function run(): void
    {
        $facility = Facility::query()->first();
        if (! $facility) return;
        InsuranceProvider::query()->updateOrCreate(['facility_id'=>$facility->id,'code'=>'NHIF'], ['name'=>'National Health Insurance Fund','provider_type'=>'nhif','is_active'=>true]);
    }
}
