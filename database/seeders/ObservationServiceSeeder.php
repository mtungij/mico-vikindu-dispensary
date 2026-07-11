<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Facility;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServicePrice;
use Illuminate\Database\Seeder;

class ObservationServiceSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Facility::query()->get() as $facility) {
            $category = ServiceCategory::query()->where('facility_id',$facility->id)->where('code','BED')->first();
            $department = Department::query()->where('facility_id',$facility->id)->where('code','BED')->first();
            if (! $category) continue;
            foreach ([['Observation 2 Hours','OBS2'],['Observation 4 Hours','OBS4'],['Observation 6 Hours','OBS6'],['Observation 12 Hours','OBS12'],['Day Care Observation','OBSDAY'],['Overnight Observation','OBSOVN'],['Nursing Care','NURCARE'],['Oxygen Therapy','OXY'],['Nebulization','NEB'],['IV Administration','IVADM'],['Wound Dressing','DRESS']] as [$name,$code]) {
                $service = Service::query()->updateOrCreate(['facility_id'=>$facility->id,'code'=>$code], ['service_category_id'=>$category->id,'department_id'=>$department?->id,'name'=>$name,'service_type'=>'bed_rest','requires_payment'=>true,'is_active'=>true]);
                ServicePrice::query()->updateOrCreate(['facility_id'=>$facility->id,'service_id'=>$service->id,'payer_type'=>'cash','insurance_provider_id'=>null,'corporate_account_id'=>null], ['amount'=>0,'currency'=>'TZS','is_active'=>true]);
            }
        }
    }
}
