<?php

namespace Database\Seeders;

use App\Models\Vaccine;
use Illuminate\Database\Seeder;

class VaccineSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->items() as $i => [$name, $code, $disease, $minAge]) {
            Vaccine::query()->updateOrCreate(['facility_id' => null, 'code' => $code], ['name'=>$name,'disease_prevented'=>$disease,'minimum_age_days'=>$minAge,'is_system'=>true,'is_active'=>true,'sort_order'=>$i + 1]);
        }
    }
    private function items(): array { return [['BCG','BCG','Tuberculosis',0],['OPV','OPV','Polio',0],['Pentavalent','PENTA','DTP-HepB-Hib',42],['PCV','PCV','Pneumococcal disease',42],['Rotavirus','ROTA','Rotavirus',42],['IPV','IPV','Polio',98],['Measles/Rubella','MR','Measles and rubella',270],['HPV','HPV','Human papillomavirus',3285],['Tetanus-containing vaccine','TT','Tetanus',null],['Other','OTHER','Other configured vaccine',null]]; }
}
