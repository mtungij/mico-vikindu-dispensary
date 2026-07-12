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
        InsuranceProvider::query()->updateOrCreate(
            ['facility_id'=>$facility->id,'code'=>'NHIF'],
            [
                'name'=>'National Health Insurance Fund',
                'provider_type'=>'national_health_insurance',
                'claim_submission_method' => 'manual_report',
                'default_currency' => 'TZS',
                'claim_prefix' => 'NHIF',
                'notes' => 'Demo provider configuration. Configure verified NHIF contracts and rules before production use.',
                'is_active'=>true,
            ],
        );

        InsuranceProvider::query()->updateOrCreate(
            ['facility_id'=>$facility->id,'code'=>'PRIV-DEMO'],
            ['name'=>'Private Insurance Demo','provider_type'=>'private_insurance','claim_submission_method'=>'portal_upload','default_currency'=>'TZS','is_active'=>true],
        );
    }
}
