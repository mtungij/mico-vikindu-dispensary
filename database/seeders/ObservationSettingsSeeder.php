<?php

namespace Database\Seeders;

use App\Models\Facility;
use App\Models\FacilitySetting;
use Illuminate\Database\Seeder;

class ObservationSettingsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Facility::query()->get() as $facility) {
            foreach (['observation_billing_mode'=>'hourly','observation_hour_rounding'=>'round_up_hour','observation_cleaning_requires_verification'=>'false'] as $key=>$value) {
                FacilitySetting::query()->updateOrCreate(['facility_id'=>$facility->id,'key'=>$key], ['value'=>$value,'type'=>'string','group'=>'observation','is_public'=>false]);
            }
        }
    }
}
