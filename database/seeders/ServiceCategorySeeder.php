<?php

namespace Database\Seeders;

use App\Models\Facility;
use App\Models\ServiceCategory;
use Illuminate\Database\Seeder;

class ServiceCategorySeeder extends Seeder
{
    public function run(): void
    {
        $facility = Facility::query()->first();
        if (! $facility) return;
        foreach ([['Registration','REG','registration'],['Consultation','CON','consultation'],['Laboratory','LAB','laboratory'],['Pharmacy','PHA','pharmacy'],['Dental','DEN','dental'],['RCH','RCH','rch'],['Bed Rest','BED','bed_rest'],['Procedures','PRO','procedure'],['Nursing Services','NUR','nursing']] as $i => [$name,$code,$type]) {
            ServiceCategory::query()->updateOrCreate(['facility_id'=>$facility->id,'code'=>$code], ['name'=>$name,'category_type'=>$type,'sort_order'=>$i,'is_active'=>true]);
        }
    }
}
