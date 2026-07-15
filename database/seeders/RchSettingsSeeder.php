<?php

namespace Database\Seeders;

use App\Models\Facility;
use App\Models\FacilitySetting;
use Illuminate\Database\Seeder;

class RchSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $facility = Facility::query()->first();
        if (! $facility) return;
        foreach (['payment_before_consultation' => false, 'triage_required' => true, 'emergency_override_enabled' => true, 'require_signature_for_prints' => false] as $key => $value) {
            FacilitySetting::query()->updateOrCreate(['facility_id'=>$facility->id,'key'=>"rch.{$key}"], ['value'=>$value ? '1' : '0', 'type'=>'boolean', 'group'=>'rch', 'is_public'=>false]);
        }
    }
}
