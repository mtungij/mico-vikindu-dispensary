<?php

namespace Database\Seeders;

use App\Models\Facility;
use App\Models\FacilitySetting;
use Illuminate\Database\Seeder;

class InsuranceSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            'insurance_enabled' => ['true', 'boolean'],
            'insurance_require_membership_verification' => ['true', 'boolean'],
            'insurance_allow_manual_verification' => ['true', 'boolean'],
            'insurance_allow_verification_override' => ['false', 'boolean'],
            'insurance_allow_dependants' => ['true', 'boolean'],
            'insurance_require_referral_when_configured' => ['true', 'boolean'],
            'insurance_require_authorization_when_configured' => ['true', 'boolean'],
            'insurance_block_unmapped_service_codes' => ['true', 'boolean'],
            'insurance_block_unmapped_medicine_codes' => ['true', 'boolean'],
            'insurance_require_primary_diagnosis' => ['true', 'boolean'],
            'insurance_require_provider_signature' => ['false', 'boolean'],
            'insurance_require_facility_stamp' => ['false', 'boolean'],
            'insurance_default_claim_type' => ['combined_visit', 'string'],
            'insurance_auto_prepare_claim_on_visit_completion' => ['false', 'boolean'],
            'insurance_auto_create_claim_items' => ['true', 'boolean'],
            'insurance_allow_combined_visit_claim' => ['true', 'boolean'],
            'insurance_claim_deadline_warning_days' => ['7', 'integer'],
            'insurance_allow_partial_approval' => ['true', 'boolean'],
            'insurance_allow_partial_payment' => ['true', 'boolean'],
            'insurance_require_rejection_reason' => ['true', 'boolean'],
            'insurance_require_correction_reason' => ['true', 'boolean'],
            'insurance_require_resubmission_validation' => ['true', 'boolean'],
            'insurance_ageing_basis' => ['submitted_at', 'string'],
            'insurance_default_report_layout' => ['facility_claim_report', 'string'],
            'insurance_private_attachment_max_mb' => ['5', 'integer'],
        ];

        Facility::query()->each(function (Facility $facility) use ($settings): void {
            foreach ($settings as $key => [$value, $type]) {
                FacilitySetting::query()->updateOrCreate(
                    ['facility_id' => $facility->id, 'key' => $key],
                    ['value' => $value, 'type' => $type, 'group' => 'insurance', 'is_public' => false],
                );
            }
        });
    }
}
