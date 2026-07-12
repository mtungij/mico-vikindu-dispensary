<?php

namespace Database\Seeders;

use App\Models\InsuranceClaimRejectionReason;
use Illuminate\Database\Seeder;

class InsuranceRejectionReasonSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['MEMBERSHIP_EXPIRED','Membership expired','eligibility','Renew or update membership validity.'],
            ['SERVICE_NOT_COVERED','Service not covered','exclusion','Convert excluded item to patient cash portion or remove from claim.'],
            ['MISSING_AUTHORIZATION','Missing authorization','authorization','Attach valid pre-authorization or create correction.'],
            ['MISSING_REFERRAL','Missing referral','documentation','Attach valid referral reference.'],
            ['INVALID_SERVICE_CODE','Invalid service code','coding','Update payer service code mapping.'],
            ['MISSING_DIAGNOSIS','Missing diagnosis','coding','Add primary diagnosis.'],
            ['DUPLICATE_CLAIM','Duplicate claim','duplicate','Review previous claim and submit correction if needed.'],
            ['QUANTITY_EXCEEDS_LIMIT','Quantity exceeds limit','limit','Adjust quantity or add authorization.'],
            ['PRICE_EXCEEDS_CONTRACT','Price exceeds contract','pricing','Review contract price snapshot.'],
            ['MISSING_ATTACHMENT','Missing attachment','documentation','Attach required supporting document.'],
        ];

        foreach ($rows as [$code, $name, $category, $action]) {
            InsuranceClaimRejectionReason::query()->updateOrCreate(
                ['facility_id' => null, 'insurance_provider_id' => null, 'code' => $code],
                ['name' => $name, 'category' => $category, 'correction_action' => $action, 'is_system' => true, 'is_active' => true],
            );
        }
    }
}
