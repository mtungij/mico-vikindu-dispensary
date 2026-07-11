<?php

namespace Database\Seeders;

use App\Models\Facility;
use App\Models\FacilitySetting;
use App\Models\Service;
use App\Services\FacilitySetupService;
use Illuminate\Database\Seeder;

class FacilitySettingsSeeder extends Seeder
{
    public function run(): void
    {
        $facility = Facility::query()->first();

        if ($facility === null) {
            return;
        }

        app(FacilitySetupService::class)->ensureRequiredSettingsExist($facility);
        $this->mapReceptionBillingServices($facility);
    }

    private function mapReceptionBillingServices(Facility $facility): void
    {
        foreach ([
            'new_patient_registration_service_id' => ['NEW-REG', 'NEWREG'],
            'returning_patient_registration_service_id' => ['RETURN-REG', 'RETREG'],
            'patient_card_replacement_service_id' => ['CARD-REPLACE', 'CARDREP'],
        ] as $key => $codes) {
            $service = Service::query()
                ->where('facility_id', $facility->id)
                ->whereIn('code', $codes)
                ->where('is_active', true)
                ->first();

            if ($service) {
                FacilitySetting::query()->updateOrCreate(
                    ['facility_id' => $facility->id, 'key' => $key],
                    ['value' => (string) $service->id, 'type' => 'string', 'group' => 'reception_billing', 'is_public' => false],
                );
            }
        }
    }
}
