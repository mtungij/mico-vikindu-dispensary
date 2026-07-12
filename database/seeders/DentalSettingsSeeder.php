<?php

namespace Database\Seeders;

use App\Models\Facility;
use App\Models\FacilitySetting;
use Illuminate\Database\Seeder;

class DentalSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $facility = Facility::query()->first();
        if (! $facility) return;
        foreach ([
            'dental_require_payment_before_consultation'=>'1',
            'dental_require_payment_before_procedure'=>'1',
            'dental_allow_emergency_override'=>'1',
            'dental_bill_materials_separately'=>'0',
            'dental_require_consent_for_surgery'=>'1',
            'dental_require_signature_for_report'=>'0',
            'dental_default_numbering_system'=>'fdi',
            'dental_enable_periodontal_charting'=>'1',
            'dental_enable_mixed_dentition'=>'1',
            'dental_enable_chair_assignment'=>'1',
            'dental_auto_create_follow_up'=>'0',
            'dental_attachment_max_mb'=>'10',
        ] as $key=>$value) {
            FacilitySetting::query()->updateOrCreate(['facility_id'=>$facility->id,'key'=>$key], ['value'=>$value,'type'=>in_array($value,['0','1'],true)?'boolean':'string','group'=>'dental','is_public'=>false]);
        }
    }
}
