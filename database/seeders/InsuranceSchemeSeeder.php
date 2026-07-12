<?php

namespace Database\Seeders;

use App\Models\InsuranceBenefitPackage;
use App\Models\InsuranceMembershipPlan;
use App\Models\InsuranceProvider;
use App\Models\InsuranceScheme;
use Illuminate\Database\Seeder;

class InsuranceSchemeSeeder extends Seeder
{
    public function run(): void
    {
        $userId = \App\Models\User::query()->where('is_super_admin', true)->value('id') ?? \App\Models\User::query()->value('id');

        InsuranceProvider::query()->each(function (InsuranceProvider $provider) use ($userId): void {
            $scheme = InsuranceScheme::query()->updateOrCreate(
                ['insurance_provider_id' => $provider->id, 'code' => 'STANDARD'],
                [
                    'facility_id' => $provider->facility_id,
                    'name' => $provider->code === 'NHIF' ? 'NHIF Standard Scheme' : 'Standard Scheme',
                    'scheme_type' => $provider->code === 'NHIF' ? 'government' : 'individual',
                    'requires_membership_verification' => true,
                    'requires_pre_authorization' => false,
                    'requires_referral' => false,
                    'allows_dependants' => true,
                    'allows_copayment' => true,
                    'is_active' => true,
                    'created_by' => $userId,
                ],
            );

            $package = InsuranceBenefitPackage::query()->updateOrCreate(
                ['insurance_provider_id' => $provider->id, 'code' => 'STANDARD'],
                [
                    'facility_id' => $provider->facility_id,
                    'insurance_scheme_id' => $scheme->id,
                    'name' => 'Standard Benefit Package',
                    'description' => 'Demo benefit package. Configure actual coverage and limits before production use.',
                    'is_active' => true,
                    'created_by' => $userId,
                ],
            );

            $scheme->update(['default_benefit_package_id' => $package->id]);

            InsuranceMembershipPlan::query()->updateOrCreate(
                ['insurance_scheme_id' => $scheme->id, 'code' => 'PRINCIPAL'],
                [
                    'facility_id' => $provider->facility_id,
                    'insurance_provider_id' => $provider->id,
                    'benefit_package_id' => $package->id,
                    'name' => 'Principal Member',
                    'membership_type' => 'principal',
                    'waiting_period_days' => 0,
                    'dependent_limit' => 5,
                    'is_active' => true,
                    'created_by' => $userId,
                ],
            );
        });
    }
}
