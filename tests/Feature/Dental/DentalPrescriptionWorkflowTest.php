<?php

namespace Tests\Feature\Dental;

use App\Enums\FacilityType;
use App\Enums\OwnershipType;
use App\Enums\ServiceType;
use App\Enums\VisitStatus;
use App\Livewire\Dental\Consultation as DentalConsultation;
use App\Livewire\Pharmacy\Queue as PharmacyQueue;
use App\Models\Department;
use App\Models\Facility;
use App\Models\InsuranceCoverageRule;
use App\Models\InsuranceProvider;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\MedicineUnit;
use App\Models\Patient;
use App\Models\PatientInsuranceMembership;
use App\Models\PatientPayerProfile;
use App\Models\PatientQueue;
use App\Models\PaymentMethod;
use App\Models\Permission;
use App\Models\PrescriptionItem;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServicePrice;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Visit;
use App\Services\ClinicalEncounterService;
use App\Services\DentalDiagnosisService;
use App\Services\DentalEncounterService;
use App\Services\MedicineFinancialClearanceService;
use App\Services\PaymentConfirmationService;
use App\Services\PharmacyDispensingService;
use App\Services\StockReceivingService;
use Database\Seeders\DentalFindingTypeSeeder;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\MedicineRouteSeeder;
use Database\Seeders\MedicineUnitSeeder;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\ServiceCategorySeeder;
use Database\Seeders\StockLocationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class DentalPrescriptionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthorized_user_cannot_open_dental_medicine_workflow(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->visit($admin);
        $unauthorized = User::factory()->create();

        Livewire::actingAs($unauthorized)->test(DentalConsultation::class, ['visit' => $visit])->assertForbidden();

        $this->assertDatabaseCount('dental_encounters', 0);
        $this->assertDatabaseCount('prescriptions', 0);
    }

    public function test_dental_new_medicine_selector_and_backend_reject_inactive_medicine(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->visit($admin);
        $active = $this->medicine($admin, 'Active Dental Selector Medicine', 'DEN-ACTIVE', 100);
        $inactive = $this->medicine($admin, 'Archived Dental Selector Medicine', 'DEN-ARCHIVED', 100);
        $inactive->update(['is_active' => false]);

        $component = Livewire::actingAs($admin)->test(DentalConsultation::class, ['visit' => $visit])
            ->set('activeTab', 'medicines')
            ->assertSee($active->name)
            ->assertDontSee($inactive->name);

        try {
            app(ClinicalEncounterService::class)->addPrescription(
                $component->get('dentalEncounter')->clinicalEncounter,
                ['items' => [$this->itemData($inactive, 3)]],
                $admin,
            );
            $this->fail('Inactive medicine was accepted through a tampered Dental prescription payload.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('medicine_id', $exception->errors());
        }

        $this->assertDatabaseCount('prescription_items', 0);
    }

    public function test_dental_ui_adds_edits_and_removes_items_in_one_unbilled_draft_prescription(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->visit($admin);
        $amoxicillin = $this->medicine($admin, 'Amoxicillin', 'AMOX', 100);
        $paracetamol = $this->medicine($admin, 'Paracetamol', 'PCM', 50);

        $component = Livewire::actingAs($admin)->test(DentalConsultation::class, ['visit' => $visit])
            ->set('activeTab', 'medicines')
            ->assertSee('Add Medicine')
            ->set('prescriptionItemForm.medicine_id', $amoxicillin->id)
            ->set('prescriptionItemForm.dose_choice', '1 capsule')
            ->set('prescriptionItemForm.frequency_choice', 'Three times daily')
            ->set('prescriptionItemForm.duration_value', '5')
            ->set('prescriptionItemForm.route_choice', 'Oral')
            ->assertSet('prescriptionItemForm.quantity', '15')
            ->call('addPrescription')
            ->assertHasNoErrors();

        $firstItem = PrescriptionItem::query()->sole();
        $firstItemId = $firstItem->id;
        $component->call('editPrescriptionItem', $firstItemId)
            ->set('prescriptionItemForm.duration_value', '7')
            ->assertSet('prescriptionItemForm.quantity', '21')
            ->set('prescriptionItemForm.quantity', '15')
            ->call('updatePrescriptionItem')
            ->assertHasNoErrors()
            ->set('prescriptionItemForm.medicine_id', $paracetamol->id)
            ->set('prescriptionItemForm.dose_choice', '1 tablet')
            ->set('prescriptionItemForm.frequency_choice', 'Twice daily')
            ->set('prescriptionItemForm.duration_value', '3')
            ->set('prescriptionItemForm.route_choice', 'Oral')
            ->assertSet('prescriptionItemForm.quantity', '6')
            ->call('addPrescription')
            ->assertHasNoErrors();

        $prescription = $component->get('dentalEncounter')->prescriptions()->sole();
        $this->assertSame('draft', $prescription->status->value);
        $this->assertSame(2, $prescription->items()->count());
        $this->assertSame($firstItemId, $firstItem->refresh()->id);
        $this->assertSame(15.0, (float) $firstItem->quantity);
        $this->assertDatabaseCount('invoice_items', 0);

        $secondItem = $prescription->items()->whereKeyNot($firstItemId)->sole();
        $component->call('removePrescriptionItem', $secondItem->id)->assertHasNoErrors();
        $this->assertSame(1, $prescription->items()->count());
        $this->assertSoftDeleted('prescription_items', ['id' => $secondItem->id]);
        $this->assertDatabaseCount('invoice_items', 0);
    }

    public function test_dental_completion_bills_saved_quantities_once_and_full_payment_releases_same_pharmacy_queue(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->visit($admin);
        $encounter = $this->preparedEncounter($visit, $admin);
        $amoxicillin = $this->medicine($admin, 'Amoxicillin', 'AMOX-BILL', 100);
        $paracetamol = $this->medicine($admin, 'Paracetamol', 'PCM-BILL', 50);
        $prescription = app(ClinicalEncounterService::class)->addPrescription($encounter->clinicalEncounter, [
            'items' => [$this->itemData($amoxicillin, 15)],
        ], $admin);
        app(ClinicalEncounterService::class)->addPrescription($encounter->clinicalEncounter, [
            'items' => [$this->itemData($paracetamol, 6)],
        ], $admin);

        app(DentalEncounterService::class)->complete($encounter, $admin);

        $prescription->refresh();
        $this->assertSame('awaiting_payment', $prescription->status->value);
        $this->assertSame(2, $prescription->items()->count());
        $this->assertSame(2, $visit->invoice->items()->where('reference_type', PrescriptionItem::class)->count());
        $amoxCharge = $prescription->items()->where('medicine_id', $amoxicillin->id)->sole()->invoiceItem;
        $this->assertSame(15.0, (float) $amoxCharge->quantity);
        $this->assertSame(100.0, (float) $amoxCharge->unit_price);
        $this->assertSame(1500.0, (float) $amoxCharge->gross_amount);
        $this->assertSame('dental', $amoxCharge->metadata['clinical_source']);
        $this->assertSame($encounter->id, $amoxCharge->metadata['dental_encounter_id']);
        $this->assertSame(VisitStatus::AwaitingPayment, $visit->refresh()->visit_status);

        app(DentalEncounterService::class)->complete($encounter->refresh(), $admin);
        $this->assertSame(2, $visit->invoice->items()->where('reference_type', PrescriptionItem::class)->count());

        $cash = PaymentMethod::query()->where('code', 'CASH')->firstOrFail();
        app(PaymentConfirmationService::class)->confirmPayment($visit->invoice->refresh(), $cash, 1800, $admin);
        $this->assertSame('prescribed', $prescription->refresh()->status->value);
        $this->assertSame(1, $this->activePharmacyQueues($visit));
        $this->assertSame(VisitStatus::AwaitingPharmacy, $visit->refresh()->visit_status);
        Livewire::actingAs($admin)->test(PharmacyQueue::class)->assertSee($prescription->prescription_number)->assertSee('Dental');
    }

    public function test_dental_completion_without_medicine_creates_no_prescription_or_pharmacy_work(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->visit($admin);
        $encounter = $this->preparedEncounter($visit, $admin);

        app(DentalEncounterService::class)->complete($encounter, $admin);

        $this->assertDatabaseCount('prescriptions', 0);
        $this->assertSame(0, $this->activePharmacyQueues($visit));
        $this->assertSame(VisitStatus::Completed, $visit->refresh()->visit_status);
    }

    public function test_partial_payment_caps_dental_dispensing_uses_fefo_and_label_shows_dental_source(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->visit($admin);
        $encounter = $this->preparedEncounter($visit, $admin);
        $medicine = $this->medicine($admin, 'Metronidazole', 'MET-PART', 1000);
        $prescription = app(ClinicalEncounterService::class)->addPrescription($encounter->clinicalEncounter, [
            'items' => [$this->itemData($medicine, 10)],
        ], $admin);
        app(DentalEncounterService::class)->complete($encounter, $admin);
        $item = $prescription->items()->sole();
        $location = StockLocation::query()->forCurrentFacility()->where('is_dispensing_location', true)->where('is_receiving_location', true)->firstOrFail();
        $supplier = Supplier::query()->create(['facility_id' => currentFacility()->id, 'name' => 'Dental Supplier', 'code' => 'DEN-SUP', 'phone_primary' => '0712000000', 'supplier_type' => 'pharmaceutical_wholesaler', 'is_active' => true]);
        $this->receive($admin, $medicine, $supplier, $location, 'DEN-LATE', today()->addMonths(6)->toDateString(), 10);
        $this->receive($admin, $medicine, $supplier, $location, 'DEN-EARLY', today()->addMonth()->toDateString(), 10);

        $cash = PaymentMethod::query()->where('code', 'CASH')->firstOrFail();
        app(PaymentConfirmationService::class)->confirmPayment($visit->invoice->refresh(), $cash, 6000, $admin);
        $clearance = app(MedicineFinancialClearanceService::class)->forItem($item->refresh());
        $this->assertSame('6.000', $clearance['remaining_paid_quantity']);
        $this->assertSame('4.000', $clearance['unpaid_quantity']);
        $this->assertSame(1, $this->activePharmacyQueues($visit));

        try {
            app(PharmacyDispensingService::class)->dispense($prescription->refresh(), [[
                'prescription_item_id' => $item->id, 'medicine_id' => $medicine->id, 'quantity' => 7,
            ]], $location, $admin);
            $this->fail('Unpaid medicine quantity was dispensed.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Only 6.000 units', $exception->errors()['quantity'][0]);
        }
        $this->assertSame(0, StockMovement::query()->where('movement_type', 'dispensing')->count());

        $dispensing = app(PharmacyDispensingService::class)->dispense($prescription->refresh(), [[
            'prescription_item_id' => $item->id, 'medicine_id' => $medicine->id, 'quantity' => 6,
        ]], $location, $admin);
        $this->assertSame(4.0, (float) MedicineBatch::query()->where('batch_number', 'DEN-EARLY')->value('available_quantity'));
        $this->assertSame(10.0, (float) MedicineBatch::query()->where('batch_number', 'DEN-LATE')->value('available_quantity'));
        $this->assertSame(6.0, (float) StockMovement::query()->where('movement_type', 'dispensing')->sum('quantity'));
        $this->assertSame(6.0, (float) $item->refresh()->dispensed_quantity);
        $this->assertSame(4.0, (float) $item->remaining_quantity);
        $this->assertSame(VisitStatus::AwaitingPayment, $visit->refresh()->visit_status);
        $this->actingAs($admin)->get(route('pharmacy.dispensings.labels', $dispensing))->assertOk()->assertSeeText('Source:')->assertSeeText('Dental');
    }

    public function test_insured_dental_medicine_uses_provider_price_and_existing_coverage_release(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->visit($admin);
        $provider = InsuranceProvider::query()->create(['facility_id' => currentFacility()->id, 'name' => 'Dental Insurer', 'code' => 'DEN-INS', 'provider_type' => 'private_insurance', 'claim_submission_method' => 'manual_report', 'is_active' => true]);
        $profile = PatientPayerProfile::query()->create(['facility_id' => currentFacility()->id, 'patient_id' => $visit->patient_id, 'payer_type' => 'insurance', 'insurance_provider_id' => $provider->id, 'membership_number' => 'DEN-MEM', 'coverage_status' => 'active', 'is_primary' => true, 'created_by' => $admin->id]);
        $visit->update(['payer_type' => 'insurance', 'patient_payer_profile_id' => $profile->id]);
        $membership = PatientInsuranceMembership::query()->create(['facility_id' => currentFacility()->id, 'patient_id' => $visit->patient_id, 'insurance_provider_id' => $provider->id, 'membership_number' => 'DEN-MEM', 'membership_type' => 'principal', 'verification_status' => 'verified', 'is_primary' => true, 'is_active' => true, 'created_by' => $admin->id]);
        $medicine = $this->medicine($admin, 'Insured Amoxicillin', 'AMOX-INS', 100);
        ServicePrice::query()->create(['facility_id' => currentFacility()->id, 'service_id' => $medicine->service_id, 'payer_type' => 'insurance', 'insurance_provider_id' => $provider->id, 'amount' => 250, 'currency' => 'TZS', 'is_active' => true, 'created_by' => $admin->id]);
        InsuranceCoverageRule::query()->create(['facility_id' => currentFacility()->id, 'insurance_provider_id' => $provider->id, 'rule_scope' => 'medicine', 'medicine_id' => $medicine->id, 'coverage_status' => 'covered', 'coverage_percentage' => 100, 'priority' => 100, 'is_active' => true]);
        $encounter = $this->preparedEncounter($visit->refresh(), $admin);
        $prescription = app(ClinicalEncounterService::class)->addPrescription($encounter->clinicalEncounter, ['items' => [$this->itemData($medicine, 4)]], $admin);

        app(DentalEncounterService::class)->complete($encounter, $admin);

        $invoiceItem = $prescription->items()->sole()->invoiceItem;
        $this->assertSame(250.0, (float) $invoiceItem->unit_price);
        $this->assertSame(1000.0, (float) $invoiceItem->insurance_amount);
        $this->assertSame(0.0, (float) $invoiceItem->patient_amount);
        $this->assertSame($membership->id, $invoiceItem->patient_insurance_membership_id);
        $this->assertSame('prescribed', $prescription->refresh()->status->value);
        $this->assertSame(1, $this->activePharmacyQueues($visit));
    }

    private function bootstrappedFacility(): User
    {
        $admin = User::factory()->superAdmin()->create(['email' => fake()->unique()->safeEmail()]);
        Facility::query()->create(['name' => 'Dental Test Facility', 'code' => 'DTF', 'facility_type' => FacilityType::Dispensary, 'ownership_type' => OwnershipType::Private, 'phone_primary' => '+255700000000', 'region' => 'Dar es Salaam', 'district' => 'Temeke', 'ward' => 'Vikindu', 'physical_address' => 'Vikindu', 'setup_completed_at' => now(), 'created_by' => $admin->id, 'updated_by' => $admin->id]);
        $this->seed([PermissionSeeder::class, DepartmentSeeder::class, ServiceCategorySeeder::class, MedicineUnitSeeder::class, MedicineRouteSeeder::class, StockLocationSeeder::class, PaymentMethodSeeder::class, DentalFindingTypeSeeder::class]);
        foreach (Permission::query()->pluck('name') as $permission) {
            $admin->givePermissionTo($permission);
        }
        $this->actingAs($admin);

        return $admin;
    }

    private function visit(User $admin): Visit
    {
        $department = Department::query()->forCurrentFacility()->where('code', 'DEN')->firstOrFail();
        $patient = Patient::factory()->create(['facility_id' => currentFacility()->id, 'created_by' => $admin->id]);

        return Visit::factory()->create(['facility_id' => currentFacility()->id, 'patient_id' => $patient->id, 'visit_type' => 'new_patient', 'payer_type' => 'cash', 'destination_department_id' => $department->id, 'current_department_id' => $department->id, 'visit_status' => VisitStatus::InQueue, 'created_by' => $admin->id]);
    }

    private function preparedEncounter(Visit $visit, User $admin)
    {
        $encounter = app(DentalEncounterService::class)->start($visit, $admin);
        app(DentalDiagnosisService::class)->add($encounter, ['diagnosis_name' => 'Dental infection', 'certainty' => 'confirmed', 'is_primary' => true], $admin);

        return app(DentalEncounterService::class)->saveDraft($encounter, ['clinical_summary' => 'Dental assessment completed.', 'treatment_plan_summary' => 'Treat and review.'], $admin);
    }

    private function medicine(User $admin, string $name, string $code, float $cashPrice): Medicine
    {
        $category = ServiceCategory::query()->where('facility_id', currentFacility()->id)->where('code', 'PHA')->firstOrFail();
        $service = Service::query()->create(['facility_id' => currentFacility()->id, 'service_category_id' => $category->id, 'name' => $name, 'code' => $code, 'service_type' => ServiceType::Medicine, 'requires_payment' => true, 'is_active' => true, 'created_by' => $admin->id]);
        ServicePrice::query()->create(['facility_id' => currentFacility()->id, 'service_id' => $service->id, 'payer_type' => 'cash', 'amount' => $cashPrice, 'currency' => 'TZS', 'is_active' => true, 'created_by' => $admin->id]);
        $unit = MedicineUnit::query()->forCurrentFacility()->firstOrFail();

        return Medicine::query()->create(['facility_id' => currentFacility()->id, 'service_id' => $service->id, 'purchase_unit_id' => $unit->id, 'dispensing_unit_id' => $unit->id, 'name' => $name, 'code' => $code, 'pack_size' => 1, 'purchase_to_dispensing_factor' => 1, 'reorder_level' => 0, 'is_active' => true, 'created_by' => $admin->id]);
    }

    private function itemData(Medicine $medicine, int $quantity): array
    {
        return ['medicine_id' => $medicine->id, 'medication_name' => $medicine->name, 'dose' => '1 tablet', 'frequency' => 'Three times daily', 'duration_value' => 5, 'duration_unit' => 'days', 'route' => 'Oral', 'quantity' => $quantity];
    }

    private function activePharmacyQueues(Visit $visit): int
    {
        return PatientQueue::query()->where('visit_id', $visit->id)->whereHas('department', fn ($query) => $query->where('code', 'PHA'))->whereIn('queue_status', ['waiting', 'called', 'serving'])->count();
    }

    private function receive(User $admin, Medicine $medicine, Supplier $supplier, StockLocation $location, string $batch, string $expiry, int $quantity): void
    {
        app(StockReceivingService::class)->receive(['supplier_id' => $supplier->id, 'stock_location_id' => $location->id], [['medicine_id' => $medicine->id, 'batch_number' => $batch, 'expiry_date' => $expiry, 'quantity_received' => $quantity, 'unit_cost' => 10]], $admin);
    }
}
