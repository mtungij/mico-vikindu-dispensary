<?php

namespace Database\Seeders;

use App\Models\DentalConsentTemplate;
use Illuminate\Database\Seeder;

class DentalConsentTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->templates() as $row) {
            DentalConsentTemplate::query()->updateOrCreate(['facility_id'=>null,'code'=>$row['code']], [...$row,'is_active'=>true]);
        }
    }

    private function templates(): array
    {
        return [
            ['name'=>'Extraction Consent','code'=>'EXTRACTION','consent_type'=>'extraction','content'=>'Ridhaa ya kungoa jino baada ya maelezo ya utaratibu.','risks'=>'Maumivu, kutokwa damu, infection, au complications nyingine.','alternatives'=>'Matibabu mbadala hutegemea hali ya jino.'],
            ['name'=>'Root Canal Consent','code'=>'ROOT_CANAL','consent_type'=>'root_canal','content'=>'Ridhaa ya matibabu ya mzizi wa jino.','risks'=>'Maumivu, failure ya matibabu, au hitaji la matibabu zaidi.','alternatives'=>'Extraction au referral kulingana na hali.'],
            ['name'=>'Orthodontic Consent','code'=>'ORTHODONTICS','consent_type'=>'orthodontics','content'=>'Ridhaa ya orthodontic assessment/treatment.','risks'=>'Discomfort, gum issues, root resorption foundation.','alternatives'=>'No treatment au referral.'],
            ['name'=>'Cosmetic Consent','code'=>'COSMETIC','consent_type'=>'cosmetic','content'=>'Ridhaa ya huduma ya cosmetic dental.','risks'=>'Sensitivity, color mismatch, au matokeo yasiyolingana na matarajio.','alternatives'=>'Alternative treatment options.'],
        ];
    }
}
