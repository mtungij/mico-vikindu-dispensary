<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Facility;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Database\Seeder;

class DentalServiceSeeder extends Seeder
{
    public function run(): void
    {
        $facility = Facility::query()->first();
        if (! $facility) return;
        $department = Department::query()->where('facility_id',$facility->id)->where('code','DEN')->first();
        $category = ServiceCategory::query()->firstOrCreate(['facility_id'=>$facility->id,'code'=>'DEN'], ['name'=>'Dental','category_type'=>'dental','department_id'=>$department?->id,'is_active'=>true]);
        foreach ($this->items() as [$name,$code,$type]) {
            $service = Service::query()
                ->where('facility_id', $facility->id)
                ->where(fn ($query) => $query->where('code', $code)->orWhere('name', $name))
                ->first() ?? new Service(['facility_id' => $facility->id]);
            $service->fill(['code'=>$code,'service_category_id'=>$category->id,'department_id'=>$department?->id,'name'=>$name,'service_type'=>$type,'requires_payment'=>true,'is_active'=>true])->save();
        }
    }
    private function items(): array
    {
        return [
            ['Dental Check-up','DENT-CHECK','dental_service'],['Oral Examination','DENT-ORAL-EXAM','consultation'],['Scaling','DENT-SCALING','dental_service'],['Polishing','DENT-POLISH','dental_service'],['Scaling and Polishing','DENT-SCALE-POLISH','dental_service'],['Fluoride Treatment','DENT-FLUORIDE','dental_service'],['Pit and Fissure Sealant','DENT-SEALANT','dental_service'],['Oral Hygiene Education','DENT-OHE','dental_service'],['Temporary Filling','DENT-TEMP-FILL','dental_service'],['Composite Filling','DENT-COMP-FILL','dental_service'],['Amalgam Filling','DENT-AMALGAM','dental_service'],['Glass Ionomer Filling','DENT-GIC','dental_service'],['Permanent Filling','DENT-PERM-FILL','dental_service'],['Crown Preparation','DENT-CROWN-PREP','dental_service'],['Crown Fitting','DENT-CROWN-FIT','dental_service'],['Bridge','DENT-BRIDGE','dental_service'],['Root Canal Treatment','DENT-RCT','dental_service'],['Pulpotomy','DENT-PULPOTOMY','dental_service'],['Denture Repair','DENT-DENTURE-REPAIR','dental_service'],['Orthodontic Consultation','DENT-ORTHO-CONS','consultation'],['Braces Fitting','DENT-BRACES-FIT','dental_service'],['Braces Adjustment','DENT-BRACES-ADJ','dental_service'],['Retainer Fitting','DENT-RETAINER','dental_service'],['Simple Extraction','DENT-EXT-SIMPLE','dental_service'],['Surgical Extraction','DENT-EXT-SURG','dental_service'],['Wisdom Tooth Extraction','DENT-EXT-WISDOM','dental_service'],['Incision and Drainage','DENT-IANDD','dental_service'],['Suturing','DENT-SUTURE','dental_service'],['Teeth Whitening','DENT-WHITEN','dental_service'],['Cosmetic Filling','DENT-COS-FILL','dental_service'],['Smile Consultation','DENT-SMILE','consultation'],['Periodontal Consultation','DENT-PERIO-CONS','consultation'],['Deep Scaling','DENT-DEEP-SCALING','dental_service'],['Root Planing','DENT-ROOT-PLANING','dental_service'],['Dental X-ray referral','DENT-XRAY-REF','dental_service'],['Local Anaesthesia','DENT-LA','dental_service'],['Dental Dressing','DENT-DRESS','dental_service'],['Post-procedure Observation','DENT-OBS','dental_service'],
        ];
    }
}
