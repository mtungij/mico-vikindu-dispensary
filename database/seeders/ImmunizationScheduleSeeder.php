<?php

namespace Database\Seeders;

use App\Models\Facility;
use App\Models\ImmunizationSchedule;
use App\Models\Vaccine;
use Illuminate\Database\Seeder;

class ImmunizationScheduleSeeder extends Seeder
{
    public function run(): void
    {
        $facility = Facility::query()->first();
        if (! $facility) return;
        $schedule = ImmunizationSchedule::query()->updateOrCreate(['facility_id'=>$facility->id,'code'=>'DEV-CHILD'], ['name'=>'Development Child Schedule','description'=>'Configurable demo schedule for development/testing; not official national policy.','target_group'=>'child','is_default'=>true,'is_active'=>true]);
        foreach ([['BCG',1,'Birth',0],['OPV',1,'OPV 0',0],['PENTA',1,'Penta 1',42],['PCV',1,'PCV 1',42],['ROTA',1,'Rota 1',42],['MR',1,'MR 1',270]] as $i => [$code,$dose,$name,$age]) {
            $vaccine = Vaccine::query()->where('code', $code)->first();
            if ($vaccine) $schedule->items()->updateOrCreate(['vaccine_id'=>$vaccine->id,'dose_number'=>$dose], ['dose_name'=>$name,'recommended_age_days'=>$age,'minimum_age_days'=>$age,'is_required'=>true,'sort_order'=>$i + 1]);
        }
    }
}
