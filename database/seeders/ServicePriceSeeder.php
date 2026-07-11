<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\ServicePrice;
use Illuminate\Database\Seeder;

class ServicePriceSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        foreach (Service::query()->get() as $service) {
            ServicePrice::query()->updateOrCreate(['facility_id'=>$service->facility_id,'service_id'=>$service->id,'payer_type'=>'cash','insurance_provider_id'=>null,'corporate_account_id'=>null], ['amount'=>0,'currency'=>'TZS','is_active'=>true]);
        }
    }
}
