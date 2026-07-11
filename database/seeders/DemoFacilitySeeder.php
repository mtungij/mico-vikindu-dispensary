<?php

namespace Database\Seeders;

use App\Enums\FacilityType;
use App\Enums\OwnershipType;
use App\Models\Facility;
use App\Models\User;
use App\Services\FacilitySetupService;
use App\Services\PhoneNumberService;
use Illuminate\Database\Seeder;

class DemoFacilitySeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('is_super_admin', true)->first();

        $facility = Facility::query()->firstOrCreate(
            ['code' => 'JMD'],
            [
                'name' => 'James Medical Dispensary',
                'facility_type' => FacilityType::Dispensary,
                'ownership_type' => OwnershipType::Private,
                'region' => 'Dar es Salaam',
                'district' => 'Kinondoni',
                'ward' => 'Kijitonyama',
                'physical_address' => 'Kijitonyama, Dar es Salaam',
                'phone_primary' => app(PhoneNumberService::class)->normalize('0700000000'),
                'created_by' => $admin?->id,
                'updated_by' => $admin?->id,
            ],
        );

        app(FacilitySetupService::class)->ensureRequiredSettingsExist($facility);
    }
}
