<?php

namespace Database\Seeders;

use App\Models\DentalMaterial;
use App\Models\Facility;
use Illuminate\Database\Seeder;

class DentalMaterialSeeder extends Seeder
{
    public function run(): void
    {
        $facility = Facility::query()->first();
        if (! $facility) return;
        foreach ([['Composite resin','COMP_RESIN','restorative','g'],['Amalgam','AMALGAM','restorative','g'],['Glass ionomer cement','GIC','restorative','g'],['Temporary filling material','TEMP_FILL','restorative','g'],['Local anaesthetic','LOCAL_AN','anaesthesia','cartridge'],['Impression material','IMP_MAT','prosthodontic','g'],['Sutures','SUTURES','surgery','pcs'],['Gloves','GLOVES','consumable','pair'],['Etching gel','ETCH','restorative','ml'],['Bonding agent','BOND','restorative','ml'],['Crown material','CROWN_MAT','prosthodontic','pcs']] as [$name,$code,$category,$unit]) {
            DentalMaterial::query()->updateOrCreate(['facility_id'=>$facility->id,'code'=>$code], ['name'=>$name,'category'=>$category,'unit'=>$unit,'track_inventory'=>false,'is_active'=>true]);
        }
    }
}
