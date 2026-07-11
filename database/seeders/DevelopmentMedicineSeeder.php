<?php

namespace Database\Seeders;

use App\Enums\ServiceType;
use App\Models\DosageForm;
use App\Models\Facility;
use App\Models\GenericMedicine;
use App\Models\Medicine;
use App\Models\MedicineCategory;
use App\Models\MedicineRoute;
use App\Models\MedicineUnit;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServicePrice;
use Illuminate\Database\Seeder;

class DevelopmentMedicineSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Facility::query()->get() as $facility) {
            $category = ServiceCategory::query()->where('facility_id', $facility->id)->where('code', 'PHA')->first();
            $unit = MedicineUnit::query()->where('facility_id', $facility->id)->where('symbol', 'tab')->first();
            $route = MedicineRoute::query()->where('facility_id', $facility->id)->where('code', 'PO')->first();
            $form = DosageForm::query()->where('facility_id', $facility->id)->where('code', 'TAB')->first();
            $medicineCategory = MedicineCategory::query()->where('facility_id', $facility->id)->where('code', 'ANAL')->first();
            $generic = GenericMedicine::query()->where('facility_id', $facility->id)->where('code', 'PAR')->first();
            if (! $category || ! $unit) {
                continue;
            }

            $service = Service::query()->updateOrCreate(['facility_id' => $facility->id, 'code' => 'MEDPAR500'], ['service_category_id' => $category->id, 'name' => 'Paracetamol 500mg Tablet', 'service_type' => ServiceType::Medicine, 'requires_payment' => true, 'is_active' => true]);
            ServicePrice::query()->updateOrCreate(['facility_id' => $facility->id, 'service_id' => $service->id, 'payer_type' => 'cash', 'insurance_provider_id' => null, 'corporate_account_id' => null], ['amount' => 100, 'currency' => 'TZS', 'is_active' => true]);

            Medicine::query()->updateOrCreate(['facility_id' => $facility->id, 'code' => 'PAR500'], [
                'medicine_category_id' => $medicineCategory?->id,
                'generic_medicine_id' => $generic?->id,
                'dosage_form_id' => $form?->id,
                'default_route_id' => $route?->id,
                'purchase_unit_id' => $unit->id,
                'dispensing_unit_id' => $unit->id,
                'service_id' => $service->id,
                'name' => 'Paracetamol 500mg Tablet',
                'strength' => '500mg',
                'pack_size' => 100,
                'purchase_to_dispensing_factor' => 1,
                'reorder_level' => 100,
                'default_dispensing_price' => 100,
                'is_active' => true,
            ]);
        }
    }
}
