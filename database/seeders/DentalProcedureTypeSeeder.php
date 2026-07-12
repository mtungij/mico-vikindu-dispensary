<?php

namespace Database\Seeders;

use App\Models\DentalProcedureType;
use Illuminate\Database\Seeder;

class DentalProcedureTypeSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->types() as $i => $row) {
            DentalProcedureType::query()->updateOrCreate(
                ['facility_id' => null, 'code' => $row['code']],
                [...$row, 'is_system' => true, 'is_active' => true, 'sort_order' => $i + 1],
            );
        }
    }

    private function types(): array
    {
        return [
            ['name'=>'Scaling and Polishing','code'=>'SCALING_POLISHING','category'=>'preventive','requires_tooth'=>false,'requires_surface'=>false,'requires_consent'=>false],
            ['name'=>'Fluoride Application','code'=>'FLUORIDE','category'=>'preventive','requires_tooth'=>false,'requires_surface'=>false,'requires_consent'=>false],
            ['name'=>'Composite Filling','code'=>'COMPOSITE_FILLING','category'=>'restorative','requires_tooth'=>true,'requires_surface'=>true,'requires_consent'=>false],
            ['name'=>'Glass Ionomer Filling','code'=>'GIC_FILLING','category'=>'restorative','requires_tooth'=>true,'requires_surface'=>true,'requires_consent'=>false],
            ['name'=>'Root Canal Treatment','code'=>'ROOT_CANAL','category'=>'endodontic','requires_tooth'=>true,'requires_surface'=>false,'requires_consent'=>true],
            ['name'=>'Simple Extraction','code'=>'SIMPLE_EXTRACTION','category'=>'oral_surgery','requires_tooth'=>true,'requires_surface'=>false,'requires_consent'=>true,'can_require_observation'=>true],
            ['name'=>'Surgical Extraction','code'=>'SURGICAL_EXTRACTION','category'=>'oral_surgery','requires_tooth'=>true,'requires_surface'=>false,'requires_consent'=>true,'can_require_observation'=>true],
            ['name'=>'Braces Adjustment','code'=>'BRACES_ADJUSTMENT','category'=>'orthodontic','requires_tooth'=>false,'requires_surface'=>false,'requires_consent'=>false],
            ['name'=>'Teeth Whitening','code'=>'TEETH_WHITENING','category'=>'cosmetic','requires_tooth'=>false,'requires_surface'=>false,'requires_consent'=>true],
        ];
    }
}
