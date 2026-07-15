<?php

namespace Database\Seeders;

use App\Models\PregnancyRiskFactorType;
use Illuminate\Database\Seeder;

class PregnancyRiskFactorSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->items() as $i => [$code, $name, $severity, $referral]) {
            PregnancyRiskFactorType::query()->updateOrCreate(['facility_id' => null, 'code' => $code], ['name' => $name, 'category' => 'obstetric', 'severity' => $severity, 'referral_recommended' => $referral, 'is_system' => true, 'is_active' => true, 'sort_order' => $i + 1]);
        }
    }
    private function items(): array { return [['previous_caesarean','Previous caesarean section','high',true],['previous_stillbirth','Previous stillbirth','high',true],['previous_pph','Previous postpartum hemorrhage','high',true],['multiple_pregnancy','Multiple pregnancy','high',true],['hypertension','Hypertension','high',true],['diabetes','Diabetes','high',true],['severe_anemia','Severe anemia','critical',true],['rh_negative','Rh negative','moderate',false],['grand_multiparity','Grand multiparity','moderate',false],['vaginal_bleeding','Vaginal bleeding','critical',true],['severe_headache','Severe headache','high',true],['convulsions','Convulsions','critical',true],['severe_abdominal_pain','Severe abdominal pain','critical',true],['reduced_fetal_movement','Reduced fetal movement','high',true],['suspected_preeclampsia','Suspected pre-eclampsia','critical',true],['prom','Premature rupture of membranes','high',true],['other','Other','moderate',false]]; }
}
