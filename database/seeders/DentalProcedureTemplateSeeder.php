<?php

namespace Database\Seeders;

use App\Models\DentalConsentTemplate;
use App\Models\DentalProcedureTemplate;
use App\Models\DentalProcedureType;
use App\Models\Facility;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Seeder;

class DentalProcedureTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $facility = Facility::query()->first();
        $admin = User::query()->where('is_super_admin', true)->first();
        if (! $facility || ! $admin) return;
        foreach ($this->templates() as $row) {
            $type = DentalProcedureType::query()->where('code', $row['type'])->first();
            if (! $type) continue;
            $service = Service::query()->where('facility_id', $facility->id)->where('name', $row['service'])->first();
            $consent = $row['consent'] ? DentalConsentTemplate::query()->where('code', $row['consent'])->first() : null;
            DentalProcedureTemplate::query()->updateOrCreate(['facility_id'=>$facility->id,'code'=>$row['code']], [
                'name'=>$row['name'],'dental_procedure_type_id'=>$type->id,'service_id'=>$service?->id,'default_diagnosis'=>$row['diagnosis'],'requires_consent'=>(bool)$consent,'consent_template_id'=>$consent?->id,'default_post_op_instructions'=>$row['instructions'],'default_follow_up_days'=>$row['follow_up'],'send_to_observation'=>$row['observation'],'is_active'=>true,'created_by'=>$admin->id,'updated_by'=>$admin->id,
            ]);
        }
    }

    private function templates(): array
    {
        return [
            ['name'=>'Simple Extraction','code'=>'TPL_SIMPLE_EXTRACTION','type'=>'SIMPLE_EXTRACTION','service'=>'Simple Extraction','consent'=>'EXTRACTION','diagnosis'=>'Non-restorable tooth','instructions'=>'Epuka kusukutua kwa nguvu kwa saa 24; rudi ikiwa damu haitakoma.','follow_up'=>7,'observation'=>false],
            ['name'=>'Composite Filling','code'=>'TPL_COMPOSITE_FILLING','type'=>'COMPOSITE_FILLING','service'=>'Composite Filling','consent'=>null,'diagnosis'=>'Dental caries','instructions'=>'Epuka kula upande uliotibiwa mpaka ganzi iishe.','follow_up'=>null,'observation'=>false],
            ['name'=>'Scaling and Polishing','code'=>'TPL_SCALING_POLISHING','type'=>'SCALING_POLISHING','service'=>'Scaling and Polishing','consent'=>null,'diagnosis'=>'Plaque/calculus deposits','instructions'=>'Endelea na usafi wa kinywa kila siku.','follow_up'=>180,'observation'=>false],
            ['name'=>'Root Canal Session','code'=>'TPL_ROOT_CANAL_SESSION','type'=>'ROOT_CANAL','service'=>'Root Canal Treatment','consent'=>'ROOT_CANAL','diagnosis'=>'Pulp disease','instructions'=>'Rudi kwa session iliyopangwa na taarifa maumivu makali.','follow_up'=>7,'observation'=>false],
        ];
    }
}
