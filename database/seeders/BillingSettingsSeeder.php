<?php

namespace Database\Seeders;

use App\Models\Facility;
use App\Models\FacilitySetting;
use Illuminate\Database\Seeder;

class BillingSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            'require_payment_before_consultation' => ['true','boolean'],
            'require_payment_before_laboratory' => ['true','boolean'],
            'require_payment_before_pharmacy' => ['true','boolean'],
            'require_payment_before_dental_procedure' => ['true','boolean'],
            'require_payment_before_rch_service' => ['true','boolean'],
            'require_payment_before_observation' => ['true','boolean'],
            'require_payment_before_general_procedure' => ['true','boolean'],
            'insurance_skip_billing_if_fully_covered' => ['true','boolean'],
            'corporate_skip_billing_if_fully_covered' => ['false','boolean'],
            'billing_release_mode' => ['handoff_items','string'],
            'billing_allow_partial_payment' => ['true','boolean'],
            'billing_partial_payment_can_release_patient' => ['false','boolean'],
            'billing_require_cashier_session' => ['false','boolean'],
            'billing_require_opening_float' => ['false','boolean'],
            'billing_allow_zero_float' => ['true','boolean'],
            'billing_require_session_for_non_cash' => ['false','boolean'],
            'billing_auto_prompt_open_session' => ['true','boolean'],
            'billing_allow_cashier_self_open' => ['true','boolean'],
            'billing_require_supervisor_session_approval' => ['false','boolean'],
            'billing_allow_emergency_override' => ['true','boolean'],
            'billing_allow_credit_patients' => ['false','boolean'],
            'billing_allow_patient_deposits' => ['true','boolean'],
            'billing_allow_overpayment' => ['false','boolean'],
            'billing_auto_print_receipt' => ['false','boolean'],
            'billing_require_payment_reference_for_mobile_money' => ['true','boolean'],
            'billing_require_payment_reference_for_bank' => ['true','boolean'],
            'billing_require_discount_approval' => ['true','boolean'],
            'billing_require_waiver_approval' => ['true','boolean'],
            'billing_require_refund_approval' => ['true','boolean'],
            'billing_require_reversal_approval' => ['true','boolean'],
        ];

        Facility::query()->each(function (Facility $facility) use ($settings): void {
            foreach ($settings as $key => [$value, $type]) {
                FacilitySetting::query()->updateOrCreate(['facility_id' => $facility->id, 'key' => $key], ['value' => $value, 'type' => $type, 'group' => 'billing', 'is_public' => false]);
            }
        });
    }
}
