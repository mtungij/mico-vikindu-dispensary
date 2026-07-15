<?php

namespace Database\Seeders;

use App\Models\FamilyPlanningMethod;
use Illuminate\Database\Seeder;

class FamilyPlanningMethodSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->items() as $i => [$name, $code, $category, $days, $procedure, $prescription, $inventory]) {
            FamilyPlanningMethod::query()->updateOrCreate(['facility_id' => null, 'code' => $code], ['name'=>$name,'category'=>$category,'duration_days'=>$days,'requires_procedure'=>$procedure,'requires_prescription'=>$prescription,'requires_inventory_item'=>$inventory,'is_system'=>true,'is_active'=>true,'sort_order'=>$i + 1]);
        }
    }
    private function items(): array { return [['Combined Oral Contraceptive','COC','short_acting',28,false,true,true],['Progestin-only Pill','POP','short_acting',28,false,true,true],['Injectable Contraceptive','INJ','short_acting',90,false,true,true],['Implant','IMP','long_acting',1095,true,false,true],['Copper IUD','IUD','long_acting',3650,true,false,true],['Male Condom','MCON','barrier',1,false,false,true],['Female Condom','FCON','barrier',1,false,false,true],['Emergency Contraception','EC','emergency',5,false,true,true],['Lactational Amenorrhea Method','LAM','natural',180,false,false,false],['Fertility Awareness','FAM','natural',null,false,false,false],['Tubal Ligation Referral','TLREF','referral',null,true,false,false],['Vasectomy Referral','VASREF','referral',null,true,false,false]]; }
}
