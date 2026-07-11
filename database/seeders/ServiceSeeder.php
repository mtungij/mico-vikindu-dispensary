<?php

namespace Database\Seeders;

use App\Enums\ServiceType;
use App\Models\Department;
use App\Models\Facility;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $facility = Facility::query()->first();
        if (! $facility) return;
        $departments = Department::query()->where('facility_id', $facility->id)->pluck('id', 'code');
        $items = [
            'REG' => [
                ['New Patient Registration','NEW-REG', ServiceType::Registration->value, null],
                ['Returning Patient Registration','RETURN-REG', ServiceType::Registration->value, null],
                ['Patient Card Replacement','CARD-REPLACE', ServiceType::AdministrativeService->value, null],
            ],
            'CON' => [
                ['General OPD Consultation','OPDCON', ServiceType::Consultation->value, 'OPD'],
                ['Follow-up Consultation','FUPCON', ServiceType::Consultation->value, 'OPD'],
                ['Dental Consultation','DENCON', ServiceType::Consultation->value, 'DEN'],
                ['RCH Consultation','RCHCON', ServiceType::Consultation->value, 'RCH'],
                ['Emergency Consultation','EMGCON', ServiceType::Consultation->value, 'OPD'],
            ],
            'RCH' => [['ANC First Visit','ANC1','rch_service'],['ANC Follow-up','ANCFUP','rch_service'],['Family Planning Consultation','FPCON','rch_service'],['Child Growth Monitoring','CGM','rch_service'],['Immunization Service','IMM','rch_service']],
            'BED' => [['Observation 2 Hours','OBS2','bed_rest'],['Observation 4 Hours','OBS4','bed_rest'],['Observation 6 Hours','OBS6','bed_rest'],['Observation 12 Hours','OBS12','bed_rest'],['Overnight Observation','OBSOVN','bed_rest']],
            'DEN' => [['Dental Check-up','DENCHECK','dental_service'],['Scaling and Polishing','SCALE','dental_service'],['Simple Extraction','EXTS','dental_service'],['Surgical Extraction','EXTSUR','dental_service'],['Temporary Filling','FILLT','dental_service'],['Permanent Filling','FILLP','dental_service'],['Teeth Whitening','WHITE','dental_service'],['Braces Consultation','BRACON','dental_service']],
        ];
        foreach ($items as $categoryCode => $services) {
            $category = ServiceCategory::query()->where('facility_id',$facility->id)->where('code',$categoryCode)->first();
            if (! $category) continue;
            foreach ($services as $item) {
                [$name, $code, $type, $departmentCode] = array_pad($item, 4, null);
                $legacyCodes = match ($code) {
                    'NEW-REG' => ['NEW-REG', 'NEWREG'],
                    'RETURN-REG' => ['RETURN-REG', 'RETREG'],
                    'CARD-REPLACE' => ['CARD-REPLACE', 'CARDREP'],
                    default => [$code],
                };
                $service = Service::query()
                    ->where('facility_id', $facility->id)
                    ->where(fn ($query) => $query->whereIn('code', $legacyCodes)->orWhere('name', $name))
                    ->first() ?? new Service(['facility_id' => $facility->id]);
                $service->fill(['code'=>$code,'service_category_id'=>$category->id,'department_id'=>$departmentCode ? ($departments[$departmentCode] ?? $category->department_id) : $category->department_id,'name'=>$name,'service_type'=>$type,'requires_payment'=>true,'is_active'=>true])->save();
            }
        }
    }
}
