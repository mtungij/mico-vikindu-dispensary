<?php

namespace Database\Seeders;

use App\Models\DentalFindingType;
use Illuminate\Database\Seeder;

class DentalFindingTypeSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->items() as $i => $item) {
            DentalFindingType::query()->updateOrCreate(['facility_id'=>null,'code'=>$item['code']], [...$item,'is_system'=>true,'is_active'=>true,'sort_order'=>$i]);
        }
    }
    private function items(): array
    {
        return [
            ['code'=>'HEALTHY','name'=>'Healthy','category'=>'normal','color'=>'#16a34a','applies_to_surface'=>false,'applies_to_whole_tooth'=>true],
            ['code'=>'CARIES','name'=>'Caries','category'=>'caries','color'=>'#dc2626','applies_to_surface'=>true,'applies_to_whole_tooth'=>true],
            ['code'=>'MISSING','name'=>'Missing','category'=>'missing','color'=>'#64748b','applies_to_surface'=>false,'applies_to_whole_tooth'=>true],
            ['code'=>'EXTRACTED','name'=>'Extracted','category'=>'missing','color'=>'#475569','applies_to_surface'=>false,'applies_to_whole_tooth'=>true],
            ['code'=>'FILLED','name'=>'Filled','category'=>'filling','color'=>'#2563eb','applies_to_surface'=>true,'applies_to_whole_tooth'=>true],
            ['code'=>'CROWNED','name'=>'Crowned','category'=>'crown','color'=>'#9333ea','applies_to_surface'=>false,'applies_to_whole_tooth'=>true],
            ['code'=>'RCT','name'=>'Root Canal Treated','category'=>'root_canal','color'=>'#7c3aed','applies_to_surface'=>false,'applies_to_whole_tooth'=>true],
            ['code'=>'FRACTURE','name'=>'Fractured','category'=>'fracture','color'=>'#ea580c','applies_to_surface'=>true,'applies_to_whole_tooth'=>true],
            ['code'=>'IMPACTED','name'=>'Impacted','category'=>'impacted','color'=>'#0f766e','applies_to_surface'=>false,'applies_to_whole_tooth'=>true],
            ['code'=>'UNERUPTED','name'=>'Unerupted','category'=>'unerupted','color'=>'#0891b2','applies_to_surface'=>false,'applies_to_whole_tooth'=>true],
            ['code'=>'RETAINED_ROOT','name'=>'Retained Root','category'=>'retained_root','color'=>'#be123c','applies_to_surface'=>false,'applies_to_whole_tooth'=>true],
            ['code'=>'MOBILE','name'=>'Mobile Tooth','category'=>'mobility','color'=>'#f59e0b','severity_enabled'=>true,'applies_to_surface'=>false,'applies_to_whole_tooth'=>true],
            ['code'=>'CALCULUS','name'=>'Calculus','category'=>'calculus','color'=>'#a16207','applies_to_surface'=>true,'applies_to_whole_tooth'=>true],
            ['code'=>'PLAQUE','name'=>'Plaque','category'=>'plaque','color'=>'#ca8a04','applies_to_surface'=>true,'applies_to_whole_tooth'=>true],
            ['code'=>'GINGIVAL_RECESSION','name'=>'Gingival Recession','category'=>'periodontal','color'=>'#db2777','severity_enabled'=>true,'applies_to_surface'=>true,'applies_to_whole_tooth'=>true],
            ['code'=>'PERIAPICAL_LESION','name'=>'Periapical Lesion','category'=>'lesion','color'=>'#e11d48','applies_to_surface'=>false,'applies_to_whole_tooth'=>true],
            ['code'=>'DISCOLORATION','name'=>'Discoloration','category'=>'discoloration','color'=>'#ca8a04','applies_to_surface'=>true,'applies_to_whole_tooth'=>true],
            ['code'=>'PLANNED','name'=>'Treatment Planned','category'=>'planned_treatment','color'=>'#f59e0b','applies_to_surface'=>true,'applies_to_whole_tooth'=>true],
            ['code'=>'COMPLETED','name'=>'Treatment Completed','category'=>'completed_treatment','color'=>'#059669','applies_to_surface'=>true,'applies_to_whole_tooth'=>true],
        ];
    }
}
