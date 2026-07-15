<?php

namespace Database\Seeders;

use App\Enums\ServiceType;
use App\Models\Department;
use App\Models\Facility;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Database\Seeder;

class RchServiceSeeder extends Seeder
{
    public function run(): void
    {
        $facility = Facility::query()->first();
        if (! $facility) return;
        $department = Department::query()->where('facility_id', $facility->id)->where('code', 'RCH')->first();
        $category = ServiceCategory::query()->where('facility_id', $facility->id)->where('code', 'RCH')->first();
        if (! $department || ! $category) return;
        foreach ($this->services() as [$name, $code]) {
            Service::query()->updateOrCreate(
                ['facility_id' => $facility->id, 'code' => $code],
                ['service_category_id' => $category->id, 'department_id' => $department->id, 'name' => $name, 'service_type' => ServiceType::RchService, 'requires_payment' => false, 'is_active' => true],
            );
        }
    }

    private function services(): array
    {
        return [
            ['ANC First Visit','ANC1'], ['ANC Follow-up','ANCFUP'], ['High-risk ANC Review','ANCHR'], ['Birth Preparedness Counselling','BPC'], ['Maternal Nutrition Counselling','MNC'],
            ['Family Planning Consultation','FPCON'], ['Family Planning Follow-up','FPFUP'], ['Contraceptive Refill','FPREFILL'], ['Injectable Administration','FPINJ'], ['Implant Insertion','FPIMPI'], ['Implant Removal','FPIMPR'], ['IUD Insertion','FPIUDI'], ['IUD Removal','FPIUDR'],
            ['Child Registration','RCHCHILD'], ['Child Growth Monitoring','CGM'], ['Nutrition Assessment','NUTASS'], ['Child Health Consultation','CHCON'],
            ['Immunization Service','IMM'], ['Vaccine Administration','VACADM'], ['Immunization Card Replacement','IMMCARD'],
        ];
    }
}
