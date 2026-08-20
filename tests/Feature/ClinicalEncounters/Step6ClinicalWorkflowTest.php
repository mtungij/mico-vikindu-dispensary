<?php

namespace Tests\Feature\ClinicalEncounters;

use App\Enums\FacilityType;
use App\Enums\OwnershipType;
use App\Enums\QueueStatus;
use App\Enums\VisitStatus;
use App\Livewire\Clinical\Icd10Search;
use App\Livewire\Opd\Consultation as OpdConsultation;
use App\Livewire\Opd\Queue as OpdQueue;
use App\Livewire\Pharmacy\Queue as PharmacyQueue;
use App\Livewire\Triage\Assessment as TriageAssessmentComponent;
use App\Livewire\Triage\Queue as TriageQueue;
use App\Models\ClinicalEncounter;
use App\Models\Department;
use App\Models\Facility;
use App\Models\Icd10Code;
use App\Models\InsuranceCoverageRule;
use App\Models\InsuranceProvider;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\LaboratoryOrder;
use App\Models\LaboratoryResult;
use App\Models\LaboratoryResultValue;
use App\Models\LaboratoryTest;
use App\Models\LaboratoryTestCategory;
use App\Models\Medicine;
use App\Models\MedicineUnit;
use App\Models\ObservationAdmission;
use App\Models\Patient;
use App\Models\PatientInsuranceMembership;
use App\Models\PatientPayerProfile;
use App\Models\PatientQueue;
use App\Models\PaymentMethod;
use App\Models\Permission;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Role;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServicePrice;
use App\Models\SpecimenType;
use App\Models\StaffProfile;
use App\Models\TriageAssessment;
use App\Models\User;
use App\Models\Visit;
use App\Services\BillingChargeService;
use App\Services\ClinicalEncounterService;
use App\Services\DiagnosisService;
use App\Services\MedicineCatalogService;
use App\Services\PaymentConfirmationService;
use App\Services\PrescriptionService;
use App\Services\ProcedureOrderService;
use App\Services\TriageService;
use App\Services\VisitClosureService;
use App\Services\VitalSignAssessmentService;
use App\Services\WorkflowService;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\MinimalIcd10Seeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class Step6ClinicalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_can_prescribe_newly_auto_configured_medicine_and_completion_uses_cash_service_price(): void
    {
        $admin = $this->bootstrappedFacility();
        $doctor = $this->staffUser('doctor');
        $unit = MedicineUnit::query()->firstOrCreate(
            ['facility_id' => currentFacility()->id, 'name' => 'Tablet'],
            ['symbol' => 'tab', 'is_active' => true, 'created_by' => $admin->id],
        );
        ServiceCategory::query()->firstOrCreate(
            ['facility_id' => currentFacility()->id, 'code' => 'PHA'],
            ['name' => 'Pharmacy', 'category_type' => 'pharmacy', 'is_active' => true, 'created_by' => $admin->id],
        );
        $medicine = app(MedicineCatalogService::class)->createMedicine([
            'name' => 'Auto-configured Amoxicillin 500mg',
            'code' => 'AUTO-AMOX500',
            'purchase_unit_id' => $unit->id,
            'dispensing_unit_id' => $unit->id,
            'pack_size' => 1,
            'purchase_to_dispensing_factor' => 1,
            'reorder_level' => 0,
            'is_active' => true,
            'cash_price' => 275,
        ], $admin);
        $visit = $this->opdVisit($admin, VisitStatus::InProgress);
        $encounter = app(ClinicalEncounterService::class)->startEncounter($visit, $doctor);

        Livewire::actingAs($doctor)->test(OpdConsultation::class, ['visit' => $visit])
            ->set('prescriptionItemForm.medicine_id', $medicine->id)
            ->set('prescriptionItemForm.dose', '1 tablet')
            ->set('prescriptionItemForm.frequency', 'OD')
            ->set('prescriptionItemForm.duration_value', '3')
            ->set('prescriptionItemForm.quantity', '3')
            ->call('addPrescription')
            ->assertHasNoErrors();
        $this->assertDatabaseCount('invoice_items', 0);
        $this->prepareEncounterForCompletion($encounter, $doctor);

        app(ClinicalEncounterService::class)->completeEncounter($encounter->refresh(), $doctor);

        $item = $encounter->prescriptions()->sole()->items()->sole();
        $this->assertNotNull($item->invoice_item_id);
        $this->assertSame($medicine->service_id, $item->invoiceItem->service_id);
        $this->assertSame(275.0, (float) $item->invoiceItem->unit_price);
        $this->assertSame(825.0, (float) $item->invoiceItem->total_amount);
    }

    public function test_medicine_without_billing_service_is_rejected_before_draft_persistence(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->opdVisit($admin, VisitStatus::InProgress);
        app(ClinicalEncounterService::class)->startEncounter($visit, $admin);
        $medicine = $this->medicine($admin);
        $medicine->update(['service_id' => null]);

        Livewire::actingAs($admin)->test(OpdConsultation::class, ['visit' => $visit])
            ->set('activeTab', 'orders')
            ->assertSee('Billing service not configured')
            ->set('prescriptionItemForm.medicine_id', $medicine->id)
            ->set('prescriptionItemForm.dose', '1 tablet')
            ->set('prescriptionItemForm.frequency', 'OD')
            ->set('prescriptionItemForm.duration_value', '3')
            ->call('addPrescription')
            ->assertHasErrors(['prescriptionItemForm.medicine_id'])
            ->assertSet('prescriptionItemForm.dose', '1 tablet')
            ->assertSet('prescriptionItemForm.medicine_id', $medicine->id);

        $this->assertDatabaseCount('prescriptions', 0);
        $this->assertDatabaseCount('prescription_items', 0);
        $this->assertDatabaseCount('invoice_items', 0);
    }

    public function test_medicine_with_no_active_or_inactive_price_is_rejected_before_add(): void
    {
        $admin = $this->bootstrappedFacility();

        foreach ([
            'missing' => [],
            'inactive' => ['is_active' => false],
            'future' => ['is_active' => true, 'effective_from' => today()->addDay()],
            'expired' => ['is_active' => true, 'effective_to' => today()->subDay()],
        ] as $case => $priceOverrides) {
            $visit = $this->opdVisit($admin, VisitStatus::InProgress);
            $encounter = app(ClinicalEncounterService::class)->startEncounter($visit, $admin);
            $medicine = $this->medicine($admin);
            $service = $medicine->service;
            $service->update(['requires_payment' => true]);
            if ($priceOverrides !== []) {
                ServicePrice::query()->create(['facility_id' => currentFacility()->id, 'service_id' => $service->id, 'payer_type' => 'cash', 'amount' => 100, 'currency' => 'TZS', 'created_by' => $admin->id, ...$priceOverrides]);
            }

            try {
                app(ClinicalEncounterService::class)->addPrescription($encounter, ['items' => [[
                    'medicine_id' => $medicine->id, 'dose' => '1 tablet', 'frequency' => 'OD',
                    'duration_value' => 3, 'duration_unit' => 'days', 'quantity' => 3,
                ]]], $admin);
                $this->fail("{$case} price was accepted.");
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('medicine_id', $exception->errors());
                $expected = match ($case) {
                    'missing' => 'no active cash billing price',
                    'inactive' => 'billing price is inactive',
                    'future' => 'billing price is not yet effective',
                    'expired' => 'billing price has expired',
                };
                $this->assertStringContainsString($expected, $exception->errors()['medicine_id'][0]);
            }
        }

        $this->assertDatabaseCount('prescriptions', 0);
        $this->assertDatabaseCount('prescription_items', 0);
    }

    public function test_wrong_facility_price_is_rejected_before_add(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->opdVisit($admin, VisitStatus::InProgress);
        $encounter = app(ClinicalEncounterService::class)->startEncounter($visit, $admin);
        $medicine = $this->medicine($admin);
        $medicine->service->update(['requires_payment' => true]);
        $otherFacility = Facility::factory()->create(['created_by' => $admin->id, 'updated_by' => $admin->id]);
        ServicePrice::query()->create(['facility_id' => $otherFacility->id, 'service_id' => $medicine->service_id, 'payer_type' => 'cash', 'amount' => 100, 'currency' => 'TZS', 'is_active' => true, 'created_by' => $admin->id]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('price is configured for another facility');
        app(ClinicalEncounterService::class)->addPrescription($encounter, ['items' => [[
            'medicine_id' => $medicine->id, 'dose' => '1 tablet', 'frequency' => 'OD',
            'duration_value' => 3, 'duration_unit' => 'days', 'quantity' => 3,
        ]]], $admin);
    }

    public function test_editing_to_unbillable_medicine_keeps_original_item_id_and_values(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->opdVisit($admin, VisitStatus::InProgress);
        $encounter = app(ClinicalEncounterService::class)->startEncounter($visit, $admin);
        $valid = $this->medicine($admin);
        $invalid = $this->medicine($admin);
        $invalid->update(['service_id' => null, 'name' => 'Unconfigured medicine']);
        $prescription = app(ClinicalEncounterService::class)->addPrescription($encounter, ['items' => [[
            'medicine_id' => $valid->id, 'dose' => '1 tablet', 'frequency' => 'OD',
            'duration_value' => 3, 'duration_unit' => 'days', 'quantity' => 3,
        ]]], $admin);
        $item = $prescription->items()->sole();

        Livewire::actingAs($admin)->test(OpdConsultation::class, ['visit' => $visit])
            ->call('editPrescriptionItem', $item->id)
            ->set('prescriptionItemForm.medicine_id', $invalid->id)
            ->call('updatePrescriptionItem')
            ->assertHasErrors(['prescriptionItemForm.medicine_id'])
            ->assertSet('editingPrescriptionItemId', $item->id);

        $this->assertSame($valid->id, $item->refresh()->medicine_id);
        $this->assertSame(1, $prescription->items()->count());
        $this->assertDatabaseCount('invoice_items', 0);
    }

    public function test_price_disabled_after_add_causes_atomic_completion_failure(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->opdVisit($admin, VisitStatus::InProgress);
        $encounter = app(ClinicalEncounterService::class)->startEncounter($visit, $admin);
        $medicine = $this->medicine($admin);
        $service = $this->service('Race condition medicine', 'MED-RACE', 'medicine', $admin);
        $medicine->update(['service_id' => $service->id]);
        $prescription = app(ClinicalEncounterService::class)->addPrescription($encounter, ['items' => [[
            'medicine_id' => $medicine->id, 'dose' => '1 tablet', 'frequency' => 'OD',
            'duration_value' => 3, 'duration_unit' => 'days', 'quantity' => 3,
        ]]], $admin);
        $this->prepareEncounterForCompletion($encounter, $admin);
        $service->prices()->where('payer_type', 'cash')->update(['is_active' => false]);

        try {
            app(ClinicalEncounterService::class)->completeEncounter($encounter->refresh(), $admin);
            $this->fail('Completion accepted a medicine whose price was disabled after draft entry.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('billing price is inactive', $exception->errors()['prescription'][0]);
        }

        $this->assertSame('in_progress', $encounter->refresh()->status->value);
        $this->assertSame('draft', $prescription->refresh()->status->value);
        $this->assertNull($prescription->items()->sole()->invoice_item_id);
        $this->assertDatabaseCount('invoice_items', 0);
        $this->assertSame(0, PatientQueue::query()->where('visit_id', $visit->id)->whereHas('department', fn ($query) => $query->where('code', 'PHA'))->count());
    }

    public function test_medicine_quantity_defaults_to_one_and_invalid_values_never_persist_from_livewire(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->opdVisit($admin, VisitStatus::InProgress);
        app(ClinicalEncounterService::class)->startEncounter($visit, $admin);
        $medicine = $this->medicine($admin);
        $component = Livewire::actingAs($admin)->test(OpdConsultation::class, ['visit' => $visit])
            ->assertSet('prescriptionItemForm.quantity', '1')
            ->set('prescriptionItemForm.medicine_id', $medicine->id)
            ->set('prescriptionItemForm.dose', '4 tablets')
            ->set('prescriptionItemForm.frequency', '4')
            ->set('prescriptionItemForm.duration_value', '4')
            ->set('prescriptionItemForm.duration_unit', 'days');

        foreach ([null, '0', '-1', 'invalid'] as $invalidQuantity) {
            $component->set('prescriptionItemForm.quantity', $invalidQuantity)
                ->call('addPrescription')
                ->assertHasErrors(['prescriptionItemForm.quantity'])
                ->assertSet('prescriptionItemForm.medicine_id', $medicine->id);
            $this->assertDatabaseCount('prescriptions', 0);
            $this->assertDatabaseCount('prescription_items', 0);
        }

        $component->set('prescriptionItemForm.quantity', '5')
            ->call('addPrescription')
            ->assertHasNoErrors()
            ->assertSet('prescriptionItemForm.quantity', '1');

        $this->assertDatabaseCount('prescriptions', 1);
        $this->assertDatabaseCount('prescription_items', 1);
        $this->assertSame('5.00', PrescriptionItem::query()->sole()->quantity);

        try {
            app(ClinicalEncounterService::class)->addPrescription(
                ClinicalEncounter::query()->where('visit_id', $visit->id)->sole(),
                ['items' => [[
                    'medicine_id' => $medicine->id, 'medication_name' => $medicine->name,
                    'dose' => '1 tablet', 'frequency' => 'TDS', 'duration_value' => 2,
                    'duration_unit' => 'days', 'quantity' => 0,
                ]]],
                $admin,
            );
            $this->fail('Service accepted an explicitly invalid medicine quantity.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('quantity', $exception->errors());
        }
        $this->assertDatabaseCount('prescription_items', 1);
    }

    public function test_legacy_missing_quantity_is_visible_and_livewire_edit_repairs_same_item_without_billing_duplication(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->opdVisit($admin, VisitStatus::InProgress);
        $encounter = app(ClinicalEncounterService::class)->startEncounter($visit, $admin);
        $medicine = $this->medicine($admin);
        $prescription = app(ClinicalEncounterService::class)->addPrescription($encounter, ['items' => [[
            'medicine_id' => $medicine->id, 'medication_name' => 'Legacy medicine', 'dose' => '1 tablet', 'frequency' => 'OD',
            'duration_value' => 2, 'duration_unit' => 'days', 'quantity' => 2,
        ]]], $admin);
        $item = $prescription->items()->firstOrFail();
        $item->update(['quantity' => null]);

        Livewire::actingAs($admin)->test(OpdConsultation::class, ['visit' => $visit])
            ->assertSee('Quantity: Missing')
            ->assertSee('Quantity must be corrected before completing consultation.')
            ->call('editPrescriptionItem', $item->id)
            ->assertSet('editingPrescriptionItemId', $item->id)
            ->set('prescriptionItemForm.frequency', 'CUSTOM')
            ->set('prescriptionItemForm.quantity', '6')
            ->call('updatePrescriptionItem')
            ->assertHasNoErrors()
            ->assertSet('editingPrescriptionItemId', null);

        $this->assertSame('6.00', $item->refresh()->quantity);
        $this->assertSame(1, PrescriptionItem::query()->where('prescription_id', $prescription->id)->count());
        $this->assertSame(1, Prescription::query()->where('clinical_encounter_id', $encounter->id)->count());
        $this->assertDatabaseCount('invoice_items', 0);
        $this->assertDatabaseHas('activity_logs', ['event' => 'prescription_item_updated', 'subject_id' => $item->id]);
    }

    public function test_doctor_edits_and_removes_same_safe_procedure_order_from_livewire(): void
    {
        $admin = $this->bootstrappedFacility();
        $doctor = $this->staffUser('doctor');
        $visit = $this->opdVisit($admin, VisitStatus::InProgress);
        $encounter = app(ClinicalEncounterService::class)->startEncounter($visit, $doctor);
        $service = $this->service('Wound dressing', 'PROC-EDIT', 'procedure', $admin);
        $service->update(['requires_payment' => false]);
        $order = app(ClinicalEncounterService::class)->addProcedureOrder($encounter, ['service_id' => $service->id], $doctor);

        Livewire::actingAs($doctor)->test(OpdConsultation::class, ['visit' => $visit])
            ->call('editProcedureOrder', $order->id)
            ->assertSet('editingProcedureOrderId', $order->id)
            ->set('procedureForm.instructions', 'Updated sterile dressing')
            ->call('updateProcedureOrder')
            ->assertHasNoErrors()
            ->assertSet('editingProcedureOrderId', null);

        $this->assertSame('Updated sterile dressing', $order->refresh()->instructions);
        $this->assertSame(1, $encounter->procedureOrders()->count());
        $this->assertDatabaseCount('invoice_items', 0);
        $this->assertDatabaseHas('activity_logs', ['event' => 'procedure_order_updated', 'subject_id' => $order->id]);

        Livewire::actingAs($doctor)->test(OpdConsultation::class, ['visit' => $visit])
            ->call('removeProcedureOrder', $order->id)
            ->assertHasNoErrors();
        $this->assertSoftDeleted('clinical_procedure_orders', ['id' => $order->id]);
        $this->assertDatabaseHas('activity_logs', ['event' => 'procedure_order_removed', 'subject_id' => $order->id]);
    }

    public function test_completed_consultation_rejects_medicine_and_procedure_mutation_at_services(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->opdVisit($admin, VisitStatus::InProgress);
        $encounter = app(ClinicalEncounterService::class)->startEncounter($visit, $admin);
        $medicine = $this->medicine($admin);
        $prescription = app(ClinicalEncounterService::class)->addPrescription($encounter, ['items' => [[
            'medicine_id' => $medicine->id, 'medication_name' => $medicine->name, 'dose' => '1 tablet', 'frequency' => 'OD',
            'duration_value' => 2, 'duration_unit' => 'days', 'quantity' => 2,
        ]]], $admin);
        $procedureService = $this->service('Terminal procedure', 'PROC-TERMINAL', 'procedure', $admin);
        $procedureService->update(['requires_payment' => false]);
        $procedure = app(ClinicalEncounterService::class)->addProcedureOrder($encounter, ['service_id' => $procedureService->id], $admin);
        $encounter->update(['status' => 'completed', 'completed_at' => now(), 'completed_by' => $admin->id]);

        foreach ([
            fn () => app(PrescriptionService::class)->updateItem($prescription->items()->firstOrFail(), [
                'medication_name' => 'Changed', 'dose' => '1 tablet', 'frequency' => 'OD',
                'duration_value' => 2, 'duration_unit' => 'days', 'quantity' => 2,
            ], $admin),
            fn () => app(ProcedureOrderService::class)->updateOrder($procedure, [
                'service_id' => $procedureService->id, 'priority' => 'normal', 'instructions' => 'Changed',
            ], $admin),
        ] as $mutation) {
            try {
                $mutation();
                $this->fail('Terminal consultation mutation was accepted.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }
    }

    public function test_billed_procedure_cannot_be_edited_or_removed_before_completion(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->opdVisit($admin, VisitStatus::InProgress);
        $encounter = app(ClinicalEncounterService::class)->startEncounter($visit, $admin);
        $service = $this->service('Paid procedure', 'PROC-BILLED-LOCK', 'procedure', $admin);
        $service->update(['requires_payment' => true]);
        $order = app(ProcedureOrderService::class)->createOrder($encounter, ['service_id' => $service->id], $admin);
        $this->assertNotNull($order->invoice_item_id);

        foreach ([
            fn () => app(ProcedureOrderService::class)->updateOrder($order, ['service_id' => $service->id, 'priority' => 'normal'], $admin),
            fn () => app(ProcedureOrderService::class)->removeOrder($order, $admin),
        ] as $mutation) {
            try {
                $mutation();
                $this->fail('Billed procedure mutation was accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('procedure', $exception->errors());
            }
        }

        $this->assertNotSoftDeleted('clinical_procedure_orders', ['id' => $order->id]);
        $this->assertSame(1, InvoiceItem::query()->whereKey($order->invoice_item_id)->count());
    }

    public function test_doctor_and_clinical_officer_can_open_and_update_their_draft_medicine_item(): void
    {
        $admin = $this->bootstrappedFacility();

        foreach (['doctor', 'clinical-officer'] as $index => $roleName) {
            $clinician = $this->staffUser($roleName);
            $visit = $this->opdVisit($admin, VisitStatus::InProgress);
            $encounter = app(ClinicalEncounterService::class)->startEncounter($visit, $clinician);
            $medicine = $this->medicine($admin);
            $prescription = app(ClinicalEncounterService::class)->addPrescription($encounter, ['items' => [[
                'medicine_id' => $medicine->id, 'medication_name' => 'Draft medicine '.$index, 'dose' => '1 tablet', 'frequency' => 'BD', 'duration_value' => 3, 'duration_unit' => 'days',
            ]]], $clinician);
            $item = $prescription->items()->firstOrFail();

            Livewire::actingAs($clinician)->test(OpdConsultation::class, ['visit' => $visit])
                ->call('editPrescriptionItem', $item->id)
                ->assertSet('editingPrescriptionItemId', $item->id)
                ->set('prescriptionItemForm.dose', '2 tablets')
                ->call('updatePrescriptionItem')
                ->assertHasNoErrors()
                ->assertSet('editingPrescriptionItemId', null);

            $this->assertSame(1, $prescription->items()->count());
            $this->assertSame('2 tablets', $item->refresh()->dose);
        }
    }

    public function test_non_clinical_roles_cannot_mutate_a_doctors_draft_prescription(): void
    {
        $admin = $this->bootstrappedFacility();
        $doctor = $this->staffUser('doctor');
        $visit = $this->opdVisit($admin, VisitStatus::InProgress);
        $encounter = app(ClinicalEncounterService::class)->startEncounter($visit, $doctor);
        $medicine = $this->medicine($admin);
        $prescription = app(ClinicalEncounterService::class)->addPrescription($encounter, ['items' => [[
            'medicine_id' => $medicine->id, 'medication_name' => 'Protected draft', 'dose' => '1 tablet', 'frequency' => 'OD', 'duration_value' => 2, 'duration_unit' => 'days',
        ]]], $doctor);

        foreach (['receptionist', 'cashier', 'pharmacist'] as $roleName) {
            $user = $this->staffUser($roleName);
            $this->assertFalse(Gate::forUser($user)->allows('update', $prescription), $roleName.' unexpectedly has prescription update permission.');
        }
    }

    public function test_doctor_can_update_and_remove_a_draft_prescription_item_without_duplication(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->opdVisit($admin, VisitStatus::InProgress);
        $encounter = app(ClinicalEncounterService::class)->startEncounter($visit, $admin);
        $medicine = $this->medicine($admin);
        $prescription = app(ClinicalEncounterService::class)->addPrescription($encounter, ['items' => [[
            'medicine_id' => $medicine->id, 'medication_name' => 'Paracetamol', 'dose' => '1 tablet', 'frequency' => 'TDS',
            'duration_value' => 5, 'duration_unit' => 'days',
        ]]], $admin);
        $item = $prescription->items()->firstOrFail();

        app(PrescriptionService::class)->updateItem($item, [
            'medicine_id' => $medicine->id, 'medication_name' => 'Paracetamol', 'dose' => '2 tablets', 'frequency' => 'BD',
            'duration_value' => 3, 'duration_unit' => 'days', 'instructions' => 'After food',
        ], $admin);

        $this->assertSame(1, $prescription->items()->count());
        $this->assertSame('12.00', $item->refresh()->quantity);
        $this->assertDatabaseHas('activity_logs', ['event' => 'prescription_item_updated', 'subject_id' => $item->id]);
        app(PrescriptionService::class)->removeItem($item, $admin);
        $this->assertSame(0, $prescription->items()->count());
        $this->assertSoftDeleted('prescriptions', ['id' => $prescription->id]);
        $this->assertDatabaseHas('activity_logs', ['event' => 'prescription_item_removed', 'subject_id' => $item->id]);
        $this->assertDatabaseHas('activity_logs', ['event' => 'empty_draft_prescription_removed', 'subject_id' => $prescription->id]);
    }

    public function test_multiple_medicines_reuse_one_encounter_draft_prescription(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->opdVisit($admin, VisitStatus::InProgress);
        $encounter = app(ClinicalEncounterService::class)->startEncounter($visit, $admin);

        foreach (['Paracetamol', 'Amoxicillin', 'Cetirizine'] as $medicineName) {
            $medicine = $this->medicine($admin);
            $medicine->update(['name' => $medicineName]);
            app(ClinicalEncounterService::class)->addPrescription($encounter, ['items' => [[
                'medicine_id' => $medicine->id,
                'medication_name' => $medicineName,
                'dose' => '1 tablet',
                'frequency' => 'OD',
                'duration_value' => 3,
                'duration_unit' => 'days',
            ]]], $admin);
        }

        $prescription = $encounter->prescriptions()->sole();
        $this->assertSame('draft', $prescription->status->value);
        $this->assertSame(3, $prescription->items()->count());
        $this->assertSame(['prescribed'], $prescription->items()->distinct()->pluck('status')->all());
    }

    public function test_three_persisted_medicines_complete_and_bill_without_empty_prescription_error(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->opdVisit($admin, VisitStatus::InProgress);
        $encounter = app(ClinicalEncounterService::class)->startEncounter($visit, $admin);

        foreach (['Paracetamol', 'Amoxicillin', 'Cetirizine'] as $medicineName) {
            $medicine = $this->medicine($admin);
            $medicine->update(['name' => $medicineName]);
            app(ClinicalEncounterService::class)->addPrescription($encounter, ['items' => [[
                'medicine_id' => $medicine->id,
                'medication_name' => $medicineName,
                'dose' => '1 tablet',
                'frequency' => 'OD',
                'duration_value' => 3,
                'duration_unit' => 'days',
            ]]], $admin);
        }

        $prescription = $encounter->prescriptions()->sole();
        $itemIds = $prescription->items()->pluck('id');
        $this->assertCount(3, $itemIds);
        $this->assertDatabaseCount('invoice_items', 0);
        $this->assertSame(0, PatientQueue::query()->where('visit_id', $visit->id)->whereHas('department', fn ($query) => $query->where('code', 'PHA'))->count());
        $this->prepareEncounterForCompletion($encounter, $admin);

        Livewire::actingAs($admin)
            ->test(OpdConsultation::class, ['visit' => $visit->refresh()])
            ->call('completeConsultation')
            ->assertHasNoErrors()
            ->assertRedirect(route('opd.index'));
        $completed = $encounter->refresh();

        $this->assertSame('completed', $completed->status->value);
        $this->assertSame('prescribed', $prescription->refresh()->status->value);
        $this->assertSame(3, PrescriptionItem::query()->whereKey($itemIds)->whereNotNull('invoice_item_id')->count());
        $this->assertSame(3, InvoiceItem::query()->where('reference_type', PrescriptionItem::class)->whereIn('reference_id', $itemIds)->count());

        app(ClinicalEncounterService::class)->completeEncounter($completed->refresh(), $admin);
        $this->assertSame(3, InvoiceItem::query()->where('reference_type', PrescriptionItem::class)->whereIn('reference_id', $itemIds)->count());
    }

    public function test_stale_empty_unbilled_draft_does_not_block_valid_medicines(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->opdVisit($admin, VisitStatus::InProgress);
        $encounter = app(ClinicalEncounterService::class)->startEncounter($visit, $admin);
        $medicine = $this->medicine($admin);
        $valid = app(ClinicalEncounterService::class)->addPrescription($encounter, ['items' => [[
            'medicine_id' => $medicine->id,
            'medication_name' => $medicine->name,
            'dose' => '1 tablet',
            'frequency' => 'OD',
            'duration_value' => 3,
            'duration_unit' => 'days',
        ]]], $admin);
        $stale = Prescription::query()->create([
            'facility_id' => $encounter->facility_id,
            'patient_id' => $encounter->patient_id,
            'visit_id' => $encounter->visit_id,
            'clinical_encounter_id' => $encounter->id,
            'prescribed_by' => $admin->id,
            'prescription_number' => 'RX-STALE-'.$encounter->id,
            'status' => 'draft',
            'prescribed_at' => now(),
            'created_by' => $admin->id,
        ]);
        $this->prepareEncounterForCompletion($encounter, $admin);

        $completed = app(ClinicalEncounterService::class)->completeEncounter($encounter->refresh(), $admin);

        $this->assertSame('completed', $completed->status->value);
        $this->assertSame('prescribed', $valid->refresh()->status->value);
        $this->assertSoftDeleted('prescriptions', ['id' => $stale->id]);
        $this->assertDatabaseHas('activity_logs', ['event' => 'empty_draft_prescription_removed', 'subject_id' => $stale->id]);
    }

    public function test_empty_draft_with_historical_billing_is_never_deleted_or_reused(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->opdVisit($admin, VisitStatus::InProgress);
        $encounter = app(ClinicalEncounterService::class)->startEncounter($visit, $admin);
        $historicalMedicine = $this->medicine($admin);
        $historical = app(ClinicalEncounterService::class)->addPrescription($encounter, ['items' => [[
            'medicine_id' => $historicalMedicine->id,
            'medication_name' => $historicalMedicine->name,
            'dose' => '1 tablet',
            'frequency' => 'OD',
            'duration_value' => 3,
            'duration_unit' => 'days',
        ]]], $admin);
        $historical = app(PrescriptionService::class)->finalizePrescription($historical, $admin);
        $historicalItem = $historical->items()->firstOrFail();
        $historical->update(['status' => 'draft']);
        $historicalItem->delete();

        $currentMedicine = $this->medicine($admin);
        $current = app(ClinicalEncounterService::class)->addPrescription($encounter, ['items' => [[
            'medicine_id' => $currentMedicine->id,
            'medication_name' => $currentMedicine->name,
            'dose' => '1 tablet',
            'frequency' => 'BD',
            'duration_value' => 3,
            'duration_unit' => 'days',
        ]]], $admin);
        $this->prepareEncounterForCompletion($encounter, $admin);

        app(ClinicalEncounterService::class)->completeEncounter($encounter->refresh(), $admin);

        $this->assertNotSame($historical->id, $current->id);
        $this->assertNotSoftDeleted('prescriptions', ['id' => $historical->id]);
        $this->assertDatabaseHas('prescription_items', ['id' => $historicalItem->id, 'invoice_item_id' => $historicalItem->invoice_item_id]);
        $this->assertSame('prescribed', $current->refresh()->status->value);
    }

    public function test_non_draft_or_dispensed_prescription_item_cannot_be_silently_edited(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->opdVisit($admin, VisitStatus::InProgress);
        $encounter = app(ClinicalEncounterService::class)->startEncounter($visit, $admin);
        $medicine = $this->medicine($admin);
        $prescription = app(ClinicalEncounterService::class)->addPrescription($encounter, ['items' => [[
            'medicine_id' => $medicine->id, 'medication_name' => 'Amoxicillin', 'dose' => '1 capsule', 'frequency' => 'TDS', 'duration_value' => 5, 'duration_unit' => 'days',
        ]]], $admin);
        $prescription->update(['status' => 'partially_dispensed']);

        $this->expectException(ValidationException::class);
        app(PrescriptionService::class)->updateItem($prescription->items()->firstOrFail(), [
            'medicine_id' => $medicine->id, 'medication_name' => 'Amoxicillin', 'dose' => '2 capsules', 'frequency' => 'TDS', 'duration_value' => 5, 'duration_unit' => 'days',
        ], $admin);
    }

    public function test_procedure_order_creates_one_linked_charge_and_recalculates_invoice(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->opdVisit($admin, VisitStatus::InProgress);
        $encounter = app(ClinicalEncounterService::class)->startEncounter($visit, $admin);
        $service = $this->service('Wound dressing', 'PROC-DRESS', 'procedure', $admin);
        $service->update(['requires_payment' => true]);

        $order = app(ProcedureOrderService::class)->createOrder($encounter, ['service_id' => $service->id], $admin);

        $this->assertNotNull($order->invoice_item_id);
        $this->assertDatabaseHas('invoice_items', ['id' => $order->invoice_item_id, 'reference_type' => $order::class, 'reference_id' => $order->id]);
        $this->assertGreaterThan(0, (float) $visit->invoice->refresh()->total_amount);
        $this->assertSame(1, $visit->invoice->items()->where('reference_type', $order::class)->where('reference_id', $order->id)->count());
    }

    public function test_guest_cannot_access_triage(): void
    {
        $this->get(route('triage.index'))->assertRedirect(route('login'));
    }

    public function test_authorized_user_can_access_triage_and_opd_queues(): void
    {
        $admin = $this->bootstrappedFacility();
        Livewire::actingAs($admin)->test(TriageQueue::class)->assertOk();
        Livewire::actingAs($admin)->test(OpdQueue::class)->assertOk();
    }

    public function test_assigned_opd_provider_can_open_consultation(): void
    {
        $admin = $this->bootstrappedFacility();
        $doctor = $this->staffUser('doctor');
        $visit = $this->opdVisit($admin, VisitStatus::InConsultation);

        ClinicalEncounter::query()->create([
            'facility_id' => currentFacility()->id,
            'patient_id' => $visit->patient_id,
            'visit_id' => $visit->id,
            'department_id' => $visit->current_department_id,
            'encounter_type' => 'opd',
            'encounter_number' => 'ENC-TEST-001',
            'provider_user_id' => $doctor->id,
            'started_at' => now(),
            'status' => 'in_progress',
            'created_by' => $doctor->id,
        ]);

        $this->actingAs($doctor)->get(route('opd.consultation', $visit))->assertOk();
    }

    public function test_authorized_opd_provider_from_same_facility_can_open_consultation(): void
    {
        $admin = $this->bootstrappedFacility();
        $doctor = $this->staffUser('doctor');
        $visit = $this->opdVisit($admin);

        $this->actingAs($doctor)->get(route('opd.consultation', $visit))
            ->assertOk()
            ->assertSee('Visit Information')
            ->assertSee('Payment / Insurance')
            ->assertDontSee('Doctor Notes')
            ->assertDontSee('wire:model.live.debounce.2000ms="form.clinical_summary"', false);

        $this->assertDatabaseHas('clinical_encounters', [
            'visit_id' => $visit->id,
            'department_id' => $visit->current_department_id,
            'provider_user_id' => $doctor->id,
        ]);
    }

    public function test_cashier_cannot_open_opd_consultation(): void
    {
        $admin = $this->bootstrappedFacility();
        $cashier = $this->staffUser('cashier');
        $visit = $this->opdVisit($admin);

        $this->actingAs($cashier)->get(route('opd.consultation', $visit))->assertForbidden();
    }

    public function test_receptionist_cannot_open_opd_consultation(): void
    {
        $admin = $this->bootstrappedFacility();
        $receptionist = $this->staffUser('receptionist');
        $visit = $this->opdVisit($admin);

        $this->actingAs($receptionist)->get(route('opd.consultation', $visit))->assertForbidden();
    }

    public function test_cross_facility_user_receives_403_for_opd_consultation(): void
    {
        $admin = $this->bootstrappedFacility();
        $otherFacility = Facility::query()->create([
            'name' => 'Other Dispensary',
            'code' => 'OTH',
            'facility_type' => FacilityType::Dispensary,
            'ownership_type' => OwnershipType::Private,
            'phone_primary' => '+255700000001',
            'region' => 'Dar es Salaam',
            'district' => 'Ilala',
            'ward' => 'Upanga',
            'physical_address' => 'Upanga',
            'setup_completed_at' => now(),
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
        $doctor = $this->staffUser('doctor', $otherFacility);
        $visit = $this->opdVisit($admin);

        $this->actingAs($doctor)->get(route('opd.consultation', $visit))->assertForbidden();
    }

    public function test_patient_routed_to_opd_after_full_payment_can_be_opened(): void
    {
        $admin = $this->bootstrappedFacility();
        $doctor = $this->staffUser('doctor');
        $opd = Department::query()->forCurrentFacility()->where('code', 'OPD')->firstOrFail();
        $opd->update(['requires_triage' => false, 'queue_enabled' => true]);
        $invoice = $this->cashInvoiceForBillingVisit($admin, $opd, 10000);
        $cash = PaymentMethod::query()->create(['name' => 'Cash', 'code' => 'CASH_TEST', 'type' => 'cash', 'is_active' => true]);

        app(PaymentConfirmationService::class)->confirmPayment($invoice, $cash, 10000, $admin);

        $visit = $invoice->visit->refresh();
        $this->assertSame(VisitStatus::InProgress, $visit->visit_status);
        $this->assertSame($opd->id, $visit->current_department_id);

        $this->actingAs($doctor)->get(route('opd.consultation', $visit))->assertOk();
    }

    public function test_visit_still_in_billing_cannot_open_opd_consultation(): void
    {
        $admin = $this->bootstrappedFacility();
        $doctor = $this->staffUser('doctor');
        $billing = Department::query()->forCurrentFacility()->where('code', 'BIL')->firstOrFail();
        $opd = Department::query()->forCurrentFacility()->where('code', 'OPD')->firstOrFail();
        $visit = $this->visitInDepartment($admin, $billing, $opd, VisitStatus::Waiting);

        $this->actingAs($doctor)->get(route('opd.consultation', $visit))->assertForbidden();
    }

    public function test_opd_summary_is_read_only_and_doctor_notes_are_in_plan_tab(): void
    {
        $admin = $this->bootstrappedFacility();
        $doctor = $this->staffUser('doctor');
        $visit = $this->opdVisit($admin);

        Livewire::actingAs($doctor)
            ->test(OpdConsultation::class, ['visit' => $visit])
            ->assertSee('Patient Demographics')
            ->assertSee('Visit Information')
            ->assertSee('Latest Triage Vitals')
            ->assertSee('Payment / Insurance')
            ->assertDontSee('Doctor Plan')
            ->assertDontSee('wire:model.live.debounce.2000ms="form.clinical_summary"', false)
            ->set('activeTab', 'plan')
            ->assertSee('Doctor Plan')
            ->assertSee('Doctor notes / clinical summary');
    }

    public function test_orders_tab_separates_lab_catalogue_from_ordered_laboratory_tests(): void
    {
        $admin = $this->bootstrappedFacility();
        $doctor = $this->staffUser('doctor');
        $visit = $this->opdVisit($admin);
        $labService = $this->service('Full Blood Picture', 'FBP', 'laboratory_test', $admin);
        $encounter = app(ClinicalEncounterService::class)->startEncounter($visit, $doctor);

        app(ClinicalEncounterService::class)->addLabOrder($encounter->refresh(), [
            'service_ids' => [$labService->id],
            'clinical_notes' => 'Rule out infection',
        ], $doctor);

        Livewire::actingAs($doctor)
            ->test(OpdConsultation::class, ['visit' => $visit->refresh()])
            ->set('activeTab', 'orders')
            ->assertSee('Laboratory Test Catalogue')
            ->assertSee('Available Tests')
            ->assertSee('Ordered Laboratory Tests')
            ->assertSee('Full Blood Picture');
    }

    public function test_duration_inputs_accept_browser_string_values(): void
    {
        $admin = $this->bootstrappedFacility();
        $doctor = $this->staffUser('clinical-officer');
        $visit = $this->opdVisit($admin);
        $medicine = $this->medicine($admin);

        $component = Livewire::actingAs($doctor)
            ->test(OpdConsultation::class, ['visit' => $visit])
            ->set('prescriptionItemForm.medicine_id', $medicine->id)
            ->set('prescriptionItemForm.dose', '500 mg')
            ->set('prescriptionItemForm.frequency', 'TDS')
            ->set('prescriptionItemForm.duration_value', '3')
            ->assertSet('prescriptionItemForm.duration_value', '3')
            ->set('complaintForm.duration_value', '2')
            ->assertSet('complaintForm.duration_value', '2');

        $this->assertSame(3, $component->instance()->prescriptionItemForm->normalize()['duration_value']);
        $this->assertSame(2, $component->instance()->complaintForm->normalize()['duration_value']);

        $component->call('addPrescription')->assertHasNoErrors();

        $this->assertDatabaseHas('prescriptions', [
            'visit_id' => $visit->id,
            'prescribed_by' => $doctor->id,
        ]);
    }

    public function test_treating_clinical_officer_can_print_consultation_summary(): void
    {
        $admin = $this->bootstrappedFacility();
        $clinicalOfficer = $this->staffUser('clinical-officer');
        $visit = $this->opdVisit($admin);
        $encounter = app(ClinicalEncounterService::class)->startEncounter($visit, $clinicalOfficer);

        $this->actingAs($clinicalOfficer)
            ->get(route('clinical-encounters.print', $encounter))
            ->assertOk();

        Livewire::actingAs($clinicalOfficer)
            ->test(OpdConsultation::class, ['visit' => $visit])
            ->call('printSummary')
            ->assertRedirect(route('clinical-encounters.print', $encounter));
    }

    public function test_opd_displays_released_single_value_with_verification_and_release_metadata(): void
    {
        $admin = $this->bootstrappedFacility();
        $doctor = $this->staffUser('doctor');
        [$visit, $result] = $this->laboratoryResultFixture($admin, $doctor, 'released', [
            ['parameter' => 'HIV Result', 'type' => 'reactive_non_reactive', 'selected_value' => 'non_reactive', 'flag' => 'normal'],
        ], 'HIV Rapid Test');

        $component = Livewire::actingAs($doctor)
            ->test(OpdConsultation::class, ['visit' => $visit])
            ->set('activeTab', 'orders')
            ->assertSee('Released')
            ->assertSee('Non-Reactive')
            ->assertSee('Laboratory remarks')
            ->assertSee('Reviewed by laboratory')
            ->assertSee('Verified by')
            ->assertSee($admin->name)
            ->assertSee($result->verified_at->format('d/m/Y H:i'))
            ->assertSee('Released at')
            ->assertSee($result->released_at->format('d/m/Y H:i'));

        $loadedItem = $component->get('encounter')->laboratoryOrders->first()->items->first();
        $this->assertTrue($loadedItem->relationLoaded('results'));
        $this->assertTrue($loadedItem->results->first()->relationLoaded('values'));
        $this->assertTrue($loadedItem->results->first()->relationLoaded('verifier'));
        $this->assertTrue($loadedItem->results->first()->relationLoaded('releaser'));
    }

    public function test_opd_displays_every_verified_parameter_unit_range_and_abnormal_flag(): void
    {
        $admin = $this->bootstrappedFacility();
        $doctor = $this->staffUser('doctor');
        [$visit] = $this->laboratoryResultFixture($admin, $doctor, 'verified', [
            ['parameter' => 'Haemoglobin', 'type' => 'numeric', 'numeric_value' => 7.5, 'unit' => 'g/dL', 'range' => '12 - 16 g/dL', 'flag' => 'low'],
            ['parameter' => 'White Blood Cells', 'type' => 'numeric', 'numeric_value' => 18.2, 'unit' => '10^9/L', 'range' => '4 - 11 10^9/L', 'flag' => 'critical_high', 'critical' => true],
        ], 'Full Blood Picture');

        Livewire::actingAs($doctor)
            ->test(OpdConsultation::class, ['visit' => $visit])
            ->set('activeTab', 'results')
            ->assertSee('Verified')
            ->assertSee('Haemoglobin')
            ->assertSee('7.5')
            ->assertSee('g/dL')
            ->assertSee('12 - 16 g/dL')
            ->assertSee('Low')
            ->assertSee('White Blood Cells')
            ->assertSee('18.2')
            ->assertSee('10^9/L')
            ->assertSee('Critical High');
    }

    public function test_opd_hides_pending_verification_values_and_uses_workflow_status(): void
    {
        $admin = $this->bootstrappedFacility();
        $doctor = $this->staffUser('doctor');
        [$visit] = $this->laboratoryResultFixture($admin, $doctor, 'pending_verification', [
            ['parameter' => 'Confidential Result', 'type' => 'text', 'text_value' => 'SECRET-PENDING-VALUE', 'flag' => 'normal'],
        ], 'Pending Test');

        Livewire::actingAs($doctor)
            ->test(OpdConsultation::class, ['visit' => $visit])
            ->set('activeTab', 'orders')
            ->assertSee('Awaiting Verification')
            ->assertDontSee('SECRET-PENDING-VALUE')
            ->assertDontSee('Result Ready');
    }

    public function test_opd_hides_cross_facility_results(): void
    {
        $admin = $this->bootstrappedFacility();
        $doctor = $this->staffUser('doctor');
        [$visit, $result] = $this->laboratoryResultFixture($admin, $doctor, 'verified', [
            ['parameter' => 'Private Result', 'type' => 'text', 'text_value' => 'FACILITY-SECRET', 'flag' => 'normal'],
        ], 'Facility Test');
        $foreignFacility = Facility::factory()->create(['created_by' => $admin->id, 'updated_by' => $admin->id]);
        $result->update(['facility_id' => $foreignFacility->id]);

        Livewire::actingAs($doctor)
            ->test(OpdConsultation::class, ['visit' => $visit])
            ->set('activeTab', 'orders')
            ->assertDontSee('FACILITY-SECRET');
    }

    public function test_opd_hides_result_values_from_doctor_without_permission(): void
    {
        $admin = $this->bootstrappedFacility();
        $doctor = $this->staffUser('doctor');
        [$visit] = $this->laboratoryResultFixture($admin, $doctor, 'verified', [
            ['parameter' => 'Private Result', 'type' => 'text', 'text_value' => 'PERMISSION-SECRET', 'flag' => 'normal'],
        ], 'Permission Test');
        $restrictedDoctor = User::factory()->create();
        StaffProfile::factory()->create(['facility_id' => currentFacility()->id, 'user_id' => $restrictedDoctor->id]);
        $restrictedDoctor->givePermissionTo(['opd.consult', 'opd.view-clinical-history']);

        Livewire::actingAs($restrictedDoctor)
            ->test(OpdConsultation::class, ['visit' => $visit])
            ->set('activeTab', 'orders')
            ->assertSee('Huna ruhusa ya kuona matokeo ya maabara.')
            ->assertDontSee('PERMISSION-SECRET');
    }

    public function test_result_ready_order_without_saved_result_is_shown_as_processing(): void
    {
        $admin = $this->bootstrappedFacility();
        $doctor = $this->staffUser('doctor');
        $visit = $this->opdVisit($admin);
        $encounter = app(ClinicalEncounterService::class)->startEncounter($visit, $doctor);
        $service = $this->service('No Saved Result', 'NO-RESULT', 'laboratory_test', $admin);
        $order = app(ClinicalEncounterService::class)->addLabOrder($encounter->refresh(), ['service_ids' => [$service->id]], $doctor);
        $order->update(['status' => 'result_ready']);

        Livewire::actingAs($doctor)
            ->test(OpdConsultation::class, ['visit' => $visit])
            ->set('activeTab', 'orders')
            ->assertSee('In Processing')
            ->assertDontSee('Result Ready');
    }

    public function test_orders_tab_uses_medicine_catalogue_and_contains_referral_orders(): void
    {
        $admin = $this->bootstrappedFacility();
        $doctor = $this->staffUser('doctor');
        $visit = $this->opdVisit($admin);
        $medicine = $this->medicine($admin);

        Livewire::actingAs($doctor)
            ->test(OpdConsultation::class, ['visit' => $visit])
            ->set('activeTab', 'orders')
            ->assertSee('Medication Orders')
            ->assertSee('Select medicine')
            ->assertSee($medicine->name)
            ->assertSee('Referral Orders')
            ->set('activeTab', 'follow')
            ->assertSee('Follow-up Appointment')
            ->assertDontSee('Referral Orders');
    }

    public function test_doctor_and_clinical_officer_can_submit_selected_laboratory_tests(): void
    {
        $admin = $this->bootstrappedFacility();

        foreach (['doctor', 'clinical-officer'] as $index => $role) {
            $clinician = $this->staffUser($role);
            $visit = $this->opdVisit($admin);
            $labService = $this->service("Laboratory Test {$index}", "LAB-AUTH-{$index}", 'laboratory_test', $admin);

            Livewire::actingAs($clinician)
                ->test(OpdConsultation::class, ['visit' => $visit])
                ->set('labForm.service_ids', [$labService->id])
                ->set('labForm.clinical_notes', 'Clinical indication')
                ->call('addLabOrder')
                ->assertHasNoErrors();

            $this->assertDatabaseHas('laboratory_orders', [
                'visit_id' => $visit->id,
                'ordered_by' => $clinician->id,
            ]);
            $this->assertDatabaseHas('laboratory_order_items', [
                'service_id' => $labService->id,
            ]);
            $this->assertDatabaseHas('invoice_items', [
                'visit_id' => $visit->id,
                'service_id' => $labService->id,
            ]);
        }
    }

    public function test_lab_order_role_permissions_follow_clinical_separation_of_duties(): void
    {
        $this->bootstrappedFacility();
        $doctor = $this->staffUser('doctor');
        $clinicalOfficer = $this->staffUser('clinical-officer');
        $receptionist = $this->staffUser('receptionist');
        $cashier = $this->staffUser('cashier');
        $laboratoryTechnician = $this->staffUser('laboratory-technician');

        foreach ([$doctor, $clinicalOfficer] as $clinician) {
            $this->assertTrue($clinician->can('opd.consult'));
            $this->assertTrue($clinician->can('diagnoses.create'));
            $this->assertTrue($clinician->can('laboratory-orders.create'));
            $this->assertTrue($clinician->can('laboratory-orders.view'));
            $this->assertFalse($clinician->can('services.view'));
            $this->assertTrue($clinician->can('laboratory-results.release'));
        }

        $this->assertFalse($receptionist->can('laboratory-orders.create'));
        $this->assertTrue($receptionist->can('laboratory-results.view'));
        $this->assertTrue($receptionist->can('laboratory-results.print'));
        $this->assertTrue($receptionist->can('laboratory-results.download'));
        $this->assertFalse($cashier->can('laboratory-orders.create'));
        $this->assertFalse($cashier->can('laboratory-results.view'));
        $this->assertFalse($cashier->can('laboratory-results.print'));
        $this->assertFalse($cashier->can('laboratory-results.download'));
        $this->assertFalse($laboratoryTechnician->can('laboratory-orders.create'));
        $this->assertTrue($laboratoryTechnician->can('laboratory-orders.view'));
        $this->assertTrue($laboratoryTechnician->can('laboratory.receive-sample'));
        $this->assertTrue($laboratoryTechnician->can('laboratory-results.enter'));
        $this->assertTrue($laboratoryTechnician->can('laboratory-results.verify'));
        $this->assertTrue($laboratoryTechnician->can('laboratory-results.release'));
        $this->assertTrue($laboratoryTechnician->can('laboratory-results.print'));
        $this->assertTrue($laboratoryTechnician->can('laboratory-results.download'));
    }

    public function test_opd_user_without_lab_order_permission_gets_an_inline_error_and_no_partial_order(): void
    {
        $admin = $this->bootstrappedFacility();
        $user = User::factory()->create();
        StaffProfile::factory()->create(['facility_id' => currentFacility()->id, 'user_id' => $user->id]);
        $user->givePermissionTo(['opd.consult', 'opd.view-clinical-history']);
        $visit = $this->opdVisit($admin);
        $labService = $this->service('Unauthorized Test', 'LAB-NO-AUTH', 'laboratory_test', $admin);

        Livewire::actingAs($user)
            ->test(OpdConsultation::class, ['visit' => $visit])
            ->set('labForm.service_ids', [$labService->id])
            ->call('addLabOrder')
            ->assertHasErrors(['labForm.service_ids']);

        $this->assertDatabaseMissing('laboratory_orders', ['visit_id' => $visit->id]);
        $this->assertDatabaseMissing('invoice_items', ['visit_id' => $visit->id, 'service_id' => $labService->id]);
    }

    public function test_cross_facility_doctor_cannot_create_an_opd_laboratory_order(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->opdVisit($admin);
        $encounter = app(ClinicalEncounterService::class)->startEncounter($visit, $admin);
        $otherFacility = Facility::query()->create([
            'name' => 'Other Laboratory Facility',
            'code' => 'OLF',
            'facility_type' => FacilityType::Dispensary,
            'ownership_type' => OwnershipType::Private,
            'phone_primary' => '+255700000002',
            'region' => 'Dar es Salaam',
            'district' => 'Ilala',
            'ward' => 'Upanga',
            'physical_address' => 'Upanga',
            'setup_completed_at' => now(),
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
        $otherDoctor = $this->staffUser('doctor', $otherFacility);
        $labService = $this->service('Facility Scoped Test', 'LAB-SCOPE', 'laboratory_test', $admin);

        try {
            app(ClinicalEncounterService::class)->addLabOrder($encounter, ['service_ids' => [$labService->id]], $otherDoctor);
            $this->fail('A cross-facility clinician was allowed to create a laboratory order.');
        } catch (AuthorizationException) {
            $this->assertDatabaseMissing('laboratory_orders', ['visit_id' => $visit->id]);
        }
    }

    public function test_doctor_cannot_order_laboratory_tests_for_a_completed_encounter(): void
    {
        $admin = $this->bootstrappedFacility();
        $doctor = $this->staffUser('doctor');
        $visit = $this->opdVisit($admin);
        $encounter = app(ClinicalEncounterService::class)->startEncounter($visit, $doctor);
        $encounter->update(['status' => 'completed', 'completed_at' => now()]);
        $labService = $this->service('Completed Visit Test', 'LAB-COMPLETE', 'laboratory_test', $admin);

        try {
            app(ClinicalEncounterService::class)->addLabOrder($encounter->refresh(), ['service_ids' => [$labService->id]], $doctor);
            $this->fail('A completed encounter was allowed to receive a laboratory order.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Laboratory orders cannot be added because this consultation is already completed.',
                $exception->errors()['encounter'][0],
            );
            $this->assertDatabaseMissing('laboratory_orders', ['visit_id' => $visit->id]);
        }
    }

    public function test_inactive_or_cross_facility_services_cannot_create_partial_lab_orders(): void
    {
        $admin = $this->bootstrappedFacility();
        $doctor = $this->staffUser('doctor');
        $visit = $this->opdVisit($admin);
        $encounter = app(ClinicalEncounterService::class)->startEncounter($visit, $doctor);
        $validService = $this->service('Valid Laboratory Test', 'LAB-VALID', 'laboratory_test', $admin);
        $inactiveService = $this->service('Inactive Laboratory Test', 'LAB-INACTIVE', 'laboratory_test', $admin);
        $inactiveService->update(['is_active' => false]);

        try {
            app(ClinicalEncounterService::class)->addLabOrder($encounter, [
                'service_ids' => [$validService->id, $inactiveService->id],
            ], $doctor);
            $this->fail('An inactive service was allowed into a laboratory order.');
        } catch (ValidationException) {
            $this->assertDatabaseMissing('laboratory_orders', ['visit_id' => $visit->id]);
            $this->assertDatabaseMissing('invoice_items', ['visit_id' => $visit->id, 'service_id' => $validService->id]);
        }

        $otherFacility = Facility::query()->create([
            'name' => 'External Service Facility',
            'code' => 'ESF',
            'facility_type' => FacilityType::Dispensary,
            'ownership_type' => OwnershipType::Private,
            'phone_primary' => '+255700000003',
            'region' => 'Dar es Salaam',
            'district' => 'Ilala',
            'ward' => 'Upanga',
            'physical_address' => 'Upanga',
            'setup_completed_at' => now(),
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
        $foreignService = Service::query()->create([
            'facility_id' => $otherFacility->id,
            'service_category_id' => $validService->service_category_id,
            'name' => 'Foreign Laboratory Test',
            'code' => 'LAB-FOREIGN',
            'service_type' => 'laboratory_test',
            'requires_payment' => true,
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        try {
            app(ClinicalEncounterService::class)->addLabOrder($encounter, ['service_ids' => [$foreignService->id]], $doctor);
            $this->fail('A cross-facility service was allowed into a laboratory order.');
        } catch (ValidationException) {
            $this->assertDatabaseMissing('laboratory_orders', ['visit_id' => $visit->id]);
            $this->assertDatabaseMissing('invoice_items', ['visit_id' => $visit->id, 'service_id' => $foreignService->id]);
        }
    }

    public function test_laboratory_order_policy_requires_an_active_opd_encounter(): void
    {
        $admin = $this->bootstrappedFacility();
        $doctor = $this->staffUser('doctor');
        $visit = $this->opdVisit($admin);
        $encounter = app(ClinicalEncounterService::class)->startEncounter($visit, $doctor);

        $this->assertTrue(Gate::forUser($doctor)->allows('create', [LaboratoryOrder::class, $encounter]));

        $encounter->update(['encounter_type' => 'dental']);

        $this->assertFalse(Gate::forUser($doctor)->allows('create', [LaboratoryOrder::class, $encounter->refresh()]));
    }

    public function test_adding_unpaid_lab_order_keeps_encounter_open_while_visit_awaits_payment(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->opdVisit($admin, VisitStatus::InProgress);
        $encounter = app(ClinicalEncounterService::class)->startEncounter($visit, $admin);
        $labService = $this->service('Malaria MRDT', 'MRDT', 'laboratory_test', $admin);

        app(ClinicalEncounterService::class)->addLabOrder($encounter->refresh(), [
            'service_ids' => [$labService->id],
            'clinical_notes' => 'Fever',
        ], $admin);

        $this->assertSame('in_progress', $encounter->refresh()->status->value);
        $this->assertSame(VisitStatus::AwaitingPharmacy, $visit->refresh()->visit_status);
    }

    public function test_missing_opd_consult_permission_returns_403(): void
    {
        $admin = $this->bootstrappedFacility();
        $user = User::factory()->create();
        StaffProfile::factory()->create(['facility_id' => currentFacility()->id, 'user_id' => $user->id]);
        $user->givePermissionTo('opd.view-queue');
        $visit = $this->opdVisit($admin);

        $this->actingAs($user)->get(route('opd.consultation', $visit))->assertForbidden();
    }

    public function test_icd10_search_matches_code_description_and_keywords_without_loading_on_empty_query(): void
    {
        Icd10Code::factory()->create([
            'code' => 'J18.9',
            'title' => 'Pneumonia, unspecified organism',
            'description' => 'Acute infection of the lung',
            'metadata' => ['keywords' => ['chest infection']],
            'is_active' => true,
        ]);
        Icd10Code::factory()->create([
            'code' => 'I10',
            'title' => 'Essential hypertension',
            'description' => 'High blood pressure',
            'is_active' => true,
        ]);

        Livewire::test(Icd10Search::class)
            ->assertDontSee('J18.9')
            ->set('query', 'J18')
            ->assertSee('J18.9')
            ->set('query', 'blood pressure')
            ->assertSee('Essential hypertension')
            ->set('query', 'chest infection')
            ->assertSee('Pneumonia, unspecified organism');
    }

    public function test_icd10_exact_code_and_prefix_results_are_ranked_first(): void
    {
        Icd10Code::factory()->create(['code' => 'AJ18', 'title' => 'Mentions J18', 'is_active' => true]);
        Icd10Code::factory()->create(['code' => 'J18.9', 'title' => 'Pneumonia', 'is_active' => true]);
        Icd10Code::factory()->create(['code' => 'J18', 'title' => 'Pneumonia exact category', 'is_active' => true]);

        $codes = Icd10Code::query()->search('j18')->pluck('code')->all();

        $this->assertSame(['J18', 'J18.9', 'AJ18'], $codes);
    }

    public function test_selecting_icd10_result_dispatches_values_and_fills_diagnosis_form(): void
    {
        $admin = $this->bootstrappedFacility();
        $doctor = $this->staffUser('doctor');
        $visit = $this->opdVisit($admin);
        $code = Icd10Code::query()->where('code', 'B54')->firstOrFail();

        Livewire::test(Icd10Search::class)
            ->set('query', 'malaria')
            ->call('selectCode', $code->id)
            ->assertSet('query', 'B54 — Unspecified malaria')
            ->assertSet('showResults', false)
            ->assertDispatched('icd10-selected', code: 'B54', title: 'Unspecified malaria');

        Livewire::actingAs($doctor)
            ->test(OpdConsultation::class, ['visit' => $visit])
            ->dispatch('icd10-selected', code: 'B54', title: 'Unspecified malaria')
            ->assertSet('diagnosisForm.icd10_code', 'B54')
            ->assertSet('diagnosisForm.diagnosis_name', 'Unspecified malaria');
    }

    public function test_doctor_can_save_selected_icd10_diagnosis_and_primary_logic_is_preserved(): void
    {
        $admin = $this->bootstrappedFacility();
        $doctor = $this->staffUser('doctor');
        $visit = $this->opdVisit($admin);
        $component = Livewire::actingAs($doctor)->test(OpdConsultation::class, ['visit' => $visit]);

        $component
            ->dispatch('icd10-selected', code: 'B54', title: 'Unspecified malaria')
            ->set('diagnosisForm.diagnosis_type', 'provisional')
            ->set('diagnosisForm.certainty', 'probable')
            ->set('diagnosisForm.is_primary', true)
            ->call('addDiagnosis')
            ->assertHasNoErrors();

        $component
            ->dispatch('icd10-selected', code: 'I10', title: 'Essential hypertension')
            ->set('diagnosisForm.diagnosis_type', 'confirmed')
            ->set('diagnosisForm.certainty', 'confirmed')
            ->set('diagnosisForm.is_primary', true)
            ->call('addDiagnosis')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('diagnoses', [
            'visit_id' => $visit->id,
            'icd10_code' => 'I10',
            'diagnosis_name' => 'Essential hypertension',
            'diagnosis_type' => 'confirmed',
            'certainty' => 'confirmed',
            'is_primary' => true,
        ]);
        $this->assertDatabaseHas('diagnoses', [
            'visit_id' => $visit->id,
            'icd10_code' => 'B54',
            'is_primary' => false,
        ]);

        $component
            ->set('diagnosisForm.icd10_code', null)
            ->set('diagnosisForm.diagnosis_name', 'Manual clinical diagnosis')
            ->set('diagnosisForm.diagnosis_type', 'provisional')
            ->set('diagnosisForm.certainty', 'suspected')
            ->set('diagnosisForm.is_primary', false)
            ->assertSet('icd10Selected', false)
            ->call('addDiagnosis')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('diagnoses', [
            'visit_id' => $visit->id,
            'icd10_code' => null,
            'diagnosis_name' => 'Manual clinical diagnosis',
        ]);
    }

    public function test_user_without_diagnosis_create_permission_cannot_add_diagnosis(): void
    {
        $admin = $this->bootstrappedFacility();
        $user = User::factory()->create();
        StaffProfile::factory()->create(['facility_id' => currentFacility()->id, 'user_id' => $user->id]);
        $user->givePermissionTo('opd.consult');
        $this->opdVisit($admin);
        $this->actingAs($user);

        $this->expectException(AuthorizationException::class);

        app(OpdConsultation::class)->addDiagnosis(app(ClinicalEncounterService::class));
    }

    public function test_diagnoses_tab_renders_search_dropdown_and_empty_catalogue_message(): void
    {
        $admin = $this->bootstrappedFacility();
        $doctor = $this->staffUser('doctor');
        $visit = $this->opdVisit($admin);

        Livewire::actingAs($doctor)
            ->test(OpdConsultation::class, ['visit' => $visit])
            ->set('activeTab', 'diagnoses')
            ->assertSee('Search ICD-10 code or diagnosis...')
            ->assertSeeLivewire(Icd10Search::class);

        Icd10Code::query()->delete();

        Livewire::test(Icd10Search::class)
            ->assertSee('No ICD-10 codes are available. Ask the administrator to import the ICD-10 catalogue.');
    }

    public function test_triage_assessment_calculates_bmi_creates_alert_and_moves_visit_to_opd_queue(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->visit($admin);

        $assessment = app(TriageService::class)->startAssessment($visit, $admin);
        $assessment = app(TriageService::class)->completeAssessment($assessment, [
            'triage_level' => 'urgent',
            'chief_complaint_summary' => 'Fever and shortness of breath',
            'temperature' => 39.8,
            'systolic_bp' => 120,
            'diastolic_bp' => 80,
            'pulse_rate' => 110,
            'oxygen_saturation' => 88,
            'weight_kg' => 80,
            'height_cm' => 180,
            'pain_score' => 7,
            'danger_signs' => [],
        ], $admin);

        $this->assertSame('24.69', $assessment->bmi);
        $this->assertDatabaseHas('clinical_alerts', ['visit_id' => $visit->id, 'alert_type' => 'abnormal_vital']);
        $this->assertSame(VisitStatus::InQueue, $visit->refresh()->visit_status);
        $this->assertDatabaseHas('patient_queues', ['visit_id' => $visit->id, 'queue_status' => 'waiting']);
    }

    public function test_triage_form_hydrates_enum_casts_as_select_values(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->visit($admin);
        $assessment = app(TriageService::class)->startAssessment($visit, $admin);
        $assessment->update([
            'triage_level' => 'urgent',
            'consciousness_level' => 'alert',
            'pregnancy_status' => 'not_applicable',
        ]);

        Livewire::actingAs($admin)
            ->test(TriageAssessmentComponent::class, ['visit' => $visit])
            ->assertSet('form.triage_level', 'urgent')
            ->assertSet('form.consciousness_level', 'alert')
            ->assertSet('form.pregnancy_status', 'not_applicable')
            ->assertSet('form.danger_signs', [])
            ->assertSet('form.allergies_confirmed', false);
    }

    public function test_nurse_can_open_triage_page(): void
    {
        $admin = $this->bootstrappedFacility();
        $nurse = $this->staffUser('nurse');
        $visit = $this->visit($admin);

        Livewire::actingAs($nurse)
            ->test(TriageAssessmentComponent::class, ['visit' => $visit])
            ->assertOk()
            ->assertSee('Kamilisha Triage');
    }

    public function test_completion_shows_inline_errors_preserves_values_and_dispatches_first_invalid_field(): void
    {
        $admin = $this->bootstrappedFacility();
        $nurse = $this->staffUser('nurse');
        $visit = $this->visit($admin);

        Livewire::actingAs($nurse)
            ->test(TriageAssessmentComponent::class, ['visit' => $visit])
            ->set('form.chief_complaint_summary', 'Persistent fever')
            ->set('form.temperature', 'invalid')
            ->call('complete')
            ->assertHasErrors([
                'form.temperature' => 'numeric',
                'form.systolic_bp' => 'required',
                'form.diastolic_bp' => 'required',
                'form.pulse_rate' => 'required',
                'form.respiratory_rate' => 'required',
                'form.oxygen_saturation' => 'required',
                'form.pain_score' => 'required',
                'form.consciousness_level' => 'required',
                'form.allergies_confirmed' => 'accepted',
            ])
            ->assertSet('form.chief_complaint_summary', 'Persistent fever')
            ->assertSet('form.temperature', 'invalid')
            ->assertDispatched('triage-validation-failed')
            ->assertSee('Hatukuweza kukamilisha Triage.')
            ->assertSee('Joto la mwili lazima liwe namba.');

        $this->assertDatabaseHas('triage_assessments', [
            'visit_id' => $visit->id,
            'status' => 'draft',
        ]);
    }

    public function test_valid_triage_completion_records_completion_and_moves_patient_queue(): void
    {
        $admin = $this->bootstrappedFacility();
        $nurse = $this->staffUser('nurse');
        $visit = $this->visit($admin);
        $component = Livewire::actingAs($nurse)->test(TriageAssessmentComponent::class, ['visit' => $visit]);

        foreach ($this->validTriageData() as $field => $value) {
            $component->set("form.{$field}", $value);
        }

        $component->call('complete')->assertRedirect(route('triage.index'));

        $assessment = TriageAssessment::query()->where('visit_id', $visit->id)->firstOrFail();
        $this->assertSame('completed', $assessment->status->value);
        $this->assertSame($nurse->id, $assessment->completed_by);
        $this->assertNotNull($assessment->completed_at);
        $this->assertDatabaseHas('activity_logs', [
            'event' => 'triage_completed',
            'subject_id' => $assessment->id,
        ]);
        $this->assertDatabaseHas('patient_queues', [
            'visit_id' => $visit->id,
            'queue_status' => 'waiting',
        ]);
    }

    public function test_unauthorized_user_cannot_complete_triage(): void
    {
        $admin = $this->bootstrappedFacility();
        $user = User::factory()->create();
        StaffProfile::factory()->create(['facility_id' => currentFacility()->id, 'user_id' => $user->id]);
        $user->givePermissionTo('triage.record-vitals');
        $visit = $this->visit($admin);
        $component = Livewire::actingAs($user)->test(TriageAssessmentComponent::class, ['visit' => $visit]);

        foreach ($this->validTriageData() as $field => $value) {
            $component->set("form.{$field}", $value);
        }

        $component->call('complete')->assertNoRedirect();

        $this->assertDatabaseHas('triage_assessments', [
            'visit_id' => $visit->id,
            'status' => 'draft',
            'completed_by' => null,
        ]);
    }

    public function test_completed_triage_cannot_be_completed_twice(): void
    {
        $admin = $this->bootstrappedFacility();
        $assessment = app(TriageService::class)->startAssessment($this->visit($admin), $admin);
        app(TriageService::class)->completeAssessment($assessment, $this->validTriageData(), $admin);
        $queueCount = PatientQueue::query()->count();

        $this->expectException(ValidationException::class);

        try {
            app(TriageService::class)->completeAssessment($assessment->refresh(), $this->validTriageData(), $admin);
        } finally {
            $this->assertSame($queueCount, PatientQueue::query()->count());
        }
    }

    public function test_incomplete_triage_can_be_saved_as_draft(): void
    {
        $admin = $this->bootstrappedFacility();
        $nurse = $this->staffUser('nurse');
        $visit = $this->visit($admin);

        Livewire::actingAs($nurse)
            ->test(TriageAssessmentComponent::class, ['visit' => $visit])
            ->set('form.chief_complaint_summary', 'Assessment is still in progress')
            ->call('saveDraft')
            ->assertHasNoErrors()
            ->assertNoRedirect();

        $this->assertDatabaseHas('triage_assessments', [
            'visit_id' => $visit->id,
            'chief_complaint_summary' => 'Assessment is still in progress',
            'status' => 'draft',
            'completed_at' => null,
        ]);
    }

    public function test_cross_facility_triage_page_is_rejected(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->visit($admin);
        $otherFacility = Facility::query()->create([
            'name' => 'Other Triage Facility',
            'code' => 'TRI-OTHER',
            'facility_type' => FacilityType::Dispensary,
            'ownership_type' => OwnershipType::Private,
            'phone_primary' => '+255700000099',
            'region' => 'Dar es Salaam',
            'district' => 'Ilala',
            'ward' => 'Upanga',
            'physical_address' => 'Upanga',
            'setup_completed_at' => now(),
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
        $visit->update(['facility_id' => $otherFacility->id]);

        $this->actingAs($admin)
            ->get(route('triage.assessment', $visit))
            ->assertNotFound();
    }

    public function test_database_failure_rolls_back_triage_completion_and_workflow(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->visit($admin);
        $assessment = app(TriageService::class)->startAssessment($visit, $admin);
        $workflow = $this->mock(WorkflowService::class);
        $workflow->shouldReceive('transferPatient')->once()->andThrow(new \RuntimeException('Simulated workflow failure'));

        try {
            app(TriageService::class)->completeAssessment($assessment, $this->validTriageData(), $admin);
            $this->fail('The simulated workflow failure was not raised.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated workflow failure', $exception->getMessage());
        }

        $assessment->refresh();
        $this->assertSame('draft', $assessment->status->value);
        $this->assertNull($assessment->completed_by);
        $this->assertNull($assessment->completed_at);
        $this->assertDatabaseMissing('activity_logs', [
            'event' => 'triage_completed',
            'subject_id' => $assessment->id,
        ]);
    }

    public function test_completed_triage_leaves_triage_and_enters_opd_queue_for_immediate_consultation(): void
    {
        $admin = $this->bootstrappedFacility();
        $nurse = $this->staffUser('nurse');
        $doctor = $this->staffUser('doctor');
        $triage = Department::query()->forCurrentFacility()->where('code', 'TRI')->firstOrFail();
        $opd = Department::query()->forCurrentFacility()->where('code', 'OPD')->firstOrFail();
        $triage->update(['queue_enabled' => true]);
        $opd->update(['queue_enabled' => true, 'requires_triage' => true]);
        $visit = $this->visitInDepartment($admin, $triage, $opd, VisitStatus::AwaitingTriage);
        $triageQueue = app(WorkflowService::class)->createQueue(
            $visit,
            $triage,
            $admin,
            VisitStatus::AwaitingTriage,
            'Triage required before OPD'
        );
        $assessment = app(TriageService::class)->startAssessment($visit->refresh(), $nurse);

        app(TriageService::class)->completeAssessment($assessment, $this->validTriageData(), $nurse);

        $visit->refresh();
        $this->assertSame(VisitStatus::InQueue, $visit->visit_status);
        $this->assertSame($opd->id, $visit->current_department_id);
        $this->assertSame($opd->id, $visit->destination_department_id);
        $this->assertSame('completed', $triageQueue->refresh()->queue_status->value);
        $this->assertDatabaseHas('patient_queues', [
            'visit_id' => $visit->id,
            'department_id' => $opd->id,
            'queue_status' => 'waiting',
        ]);
        $this->assertSame(1, PatientQueue::query()
            ->where('visit_id', $visit->id)
            ->whereIn('queue_status', ['waiting', 'called', 'serving'])
            ->count());
        $this->assertDatabaseMissing('patient_queues', [
            'visit_id' => $visit->id,
            'department_id' => $triage->id,
            'queue_status' => 'waiting',
        ]);

        Livewire::actingAs($nurse)
            ->test(TriageQueue::class)
            ->assertDontSee($visit->patient->patient_number);
        Livewire::actingAs($doctor)
            ->test(OpdQueue::class)
            ->assertSee($visit->patient->patient_number);
        $this->actingAs($doctor)
            ->get(route('opd.consultation', $visit))
            ->assertOk();
    }

    public function test_legacy_triage_destination_is_repaired_to_opd_on_completion(): void
    {
        $admin = $this->bootstrappedFacility();
        $triage = Department::query()->forCurrentFacility()->where('code', 'TRI')->firstOrFail();
        $opd = Department::query()->forCurrentFacility()->where('code', 'OPD')->firstOrFail();
        $visit = $this->visitInDepartment($admin, $triage, $triage, VisitStatus::AwaitingTriage);
        $queue = app(WorkflowService::class)->createQueue($visit, $triage, $admin, VisitStatus::AwaitingTriage, 'Legacy triage queue');
        $assessment = app(TriageService::class)->startAssessment($visit->refresh(), $admin);

        app(TriageService::class)->completeAssessment($assessment, $this->validTriageData(), $admin);

        $visit->refresh();
        $this->assertSame($opd->id, $visit->destination_department_id);
        $this->assertSame($opd->id, $visit->current_department_id);
        $this->assertSame('completed', $queue->refresh()->queue_status->value);
        $this->assertDatabaseHas('patient_queues', [
            'visit_id' => $visit->id,
            'department_id' => $opd->id,
            'queue_status' => 'waiting',
        ]);
    }

    public function test_invalid_pain_score_and_oxygen_saturation_are_rejected(): void
    {
        $this->bootstrappedFacility();
        $this->expectException(ValidationException::class);
        app(VitalSignAssessmentService::class)->validateVitalRanges(['pain_score' => 11, 'oxygen_saturation' => 101]);
    }

    public function test_clinician_can_complete_encounter_without_prior_sign_off(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->visit($admin, VisitStatus::InQueue);
        $service = app(ClinicalEncounterService::class);

        $encounter = $service->startEncounter($visit, $admin);
        $service->saveDraft($encounter, ['clinical_summary' => 'Stable patient', 'treatment_plan' => 'Oral medication', 'outcome' => 'discharged_home'], $admin);
        $service->addDiagnosis($encounter->refresh(), ['diagnosis_type' => 'final', 'diagnosis_name' => 'Fever', 'certainty' => 'confirmed', 'is_primary' => true], $admin);
        $completed = $service->completeEncounter($encounter->refresh(), $admin);

        $this->assertSame('completed', $completed->status->value);
        $this->assertSame($admin->id, $completed->signed_off_by);
        $this->assertNotNull($completed->signed_off_at);
        $this->assertNotNull($completed->signed_content_hash);
        $this->assertSame($admin->id, $completed->completed_by);
        $this->assertNotNull($completed->completed_at);
        $this->assertSame(VisitStatus::Completed, $visit->refresh()->visit_status);
        $this->assertSame($completed->id, $service->completeEncounter($completed, $admin)->id);
    }

    public function test_opd_consultation_save_draft_validates_only_consultation_draft_fields_and_stays_on_page(): void
    {
        $admin = $this->bootstrappedFacility();
        $doctor = $this->staffUser('doctor');
        $visit = $this->opdVisit($admin, VisitStatus::InProgress);

        Livewire::actingAs($doctor)
            ->test(OpdConsultation::class, ['visit' => $visit])
            ->set('form.chief_complaint', 'Intermittent headache')
            ->call('saveDraft')
            ->assertHasNoErrors()
            ->assertNoRedirect();

        $this->assertDatabaseHas('clinical_encounters', [
            'visit_id' => $visit->id,
            'chief_complaint' => 'Intermittent headache',
            'completed_at' => null,
        ]);
    }

    public function test_legacy_sign_off_service_remains_compatible_for_existing_integrations(): void
    {
        $admin = $this->bootstrappedFacility();
        $doctor = $this->staffUser('doctor');
        $visit = $this->opdVisit($admin, VisitStatus::InProgress);
        $component = Livewire::actingAs($doctor)->test(OpdConsultation::class, ['visit' => $visit]);

        $component->call('signOff')->assertHasErrors(['clinical_content']);
        $encounter = ClinicalEncounter::query()->where('visit_id', $visit->id)->firstOrFail();
        $this->assertNull($encounter->signed_off_at);

        app(DiagnosisService::class)->addDiagnosis($encounter, [
            'diagnosis_type' => 'final',
            'diagnosis_name' => 'Acute illness',
            'certainty' => 'confirmed',
            'is_primary' => true,
        ], $doctor);
        $component
            ->set('form.clinical_summary', 'Patient assessed and clinically stable')
            ->set('form.outcome', 'discharged_home')
            ->call('signOff')
            ->assertHasNoErrors();

        $encounter->refresh();
        $this->assertSame($doctor->id, $encounter->signed_off_by);
        $this->assertNotNull($encounter->signed_off_at);

        $component->call('signOff')->assertHasErrors(['encounter']);
        $this->assertSame($doctor->id, $encounter->refresh()->signed_off_by);
    }

    public function test_complete_consultation_saves_pending_changes_records_completer_and_redirects(): void
    {
        $admin = $this->bootstrappedFacility();
        $doctor = $this->staffUser('doctor');
        $visit = $this->opdVisit($admin, VisitStatus::InProgress);
        $component = Livewire::actingAs($doctor)->test(OpdConsultation::class, ['visit' => $visit]);
        $encounter = ClinicalEncounter::query()->where('visit_id', $visit->id)->firstOrFail();
        app(DiagnosisService::class)->addDiagnosis($encounter, [
            'diagnosis_type' => 'final',
            'diagnosis_name' => 'Tension headache',
            'certainty' => 'confirmed',
            'is_primary' => true,
        ], $doctor);

        $component
            ->set('form.clinical_summary', 'Patient is stable for discharge')
            ->set('form.treatment_plan', 'Hydration and analgesia')
            ->set('form.outcome', 'discharged_home')
            ->call('completeConsultation')
            ->assertHasNoErrors()
            ->assertRedirect(route('opd.index'));

        $encounter->refresh();
        $this->assertSame('completed', $encounter->status->value);
        $this->assertSame($doctor->id, $encounter->signed_off_by);
        $this->assertNotNull($encounter->signed_off_at);
        $this->assertSame($doctor->id, $encounter->completed_by);
        $this->assertNotNull($encounter->completed_at);
        $this->assertSame('Patient is stable for discharge', $encounter->clinical_summary);
        $this->assertSame(VisitStatus::Completed, $visit->refresh()->visit_status);
        $this->assertNotNull($visit->completed_at);
        $this->assertDatabaseMissing('patient_queues', [
            'visit_id' => $visit->id,
            'queue_status' => 'waiting',
        ]);
    }

    public function test_completed_consultation_refreshes_as_read_only_with_persisted_completion_details(): void
    {
        $admin = $this->bootstrappedFacility();
        $doctor = $this->staffUser('doctor');
        $visit = $this->opdVisit($admin, VisitStatus::InProgress);
        $encounter = app(ClinicalEncounterService::class)->startEncounter($visit, $doctor);
        $this->prepareEncounterForCompletion($encounter, $doctor);
        $completed = app(ClinicalEncounterService::class)->completeEncounter($encounter->refresh(), $doctor);

        $component = Livewire::actingAs($doctor)->test(OpdConsultation::class, ['visit' => $visit->refresh()])
            ->assertSet('encounter.id', $completed->id)
            ->assertSee('Consultation Completed')
            ->assertSee($doctor->name)
            ->assertSee($completed->completed_at->format('d M Y H:i'))
            ->assertSee('Discharged Home')
            ->assertSee('Print Summary')
            ->assertDontSee('Save Draft')
            ->assertDontSee('Complete Consultation')
            ->assertDontSee('Final Consultation Outcome')
            ->set('activeTab', 'orders')
            ->assertDontSee('Add Medication Order')
            ->assertDontSee('Update Medicine')
            ->assertDontSee('Add Procedure')
            ->assertDontSee('Update Procedure')
            ->assertDontSee('Required before completion:')
            ->assertDontSee('Select a final consultation outcome.');

        $originalSummary = $completed->clinical_summary;
        $component->set('form.clinical_summary', 'Must not persist')->call('saveDraft')->assertHasErrors(['encounter']);
        $this->assertSame($originalSummary, $completed->refresh()->clinical_summary);

        $completedAt = $completed->completed_at;
        $component->call('completeConsultation')->assertHasNoErrors();
        $this->assertTrue($completedAt->equalTo($completed->refresh()->completed_at));
        $this->assertSame('discharged_home', $completed->outcome->value);
    }

    public function test_referred_and_cancelled_encounters_render_terminal_read_only_status(): void
    {
        $admin = $this->bootstrappedFacility();

        foreach (['referred', 'cancelled'] as $status) {
            $visit = $this->opdVisit($admin, VisitStatus::InProgress);
            $encounter = app(ClinicalEncounterService::class)->startEncounter($visit, $admin);
            $encounter->update(['status' => $status, 'outcome' => $status === 'referred' ? 'referred' : 'ongoing']);

            Livewire::actingAs($admin)->test(OpdConsultation::class, ['visit' => $visit->refresh()])
                ->assertSee('Consultation '.str($status)->title())
                ->assertDontSee('Save Draft')
                ->assertDontSee('Complete Consultation')
                ->assertDontSee('Final Consultation Outcome');
        }
    }

    public function test_completed_consultation_rejects_diagnosis_plan_and_prescription_mutations(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->opdVisit($admin, VisitStatus::InProgress);
        $encounter = app(ClinicalEncounterService::class)->startEncounter($visit, $admin);
        $medicine = $this->medicine($admin);
        $prescription = app(ClinicalEncounterService::class)->addPrescription($encounter, ['items' => [[
            'medicine_id' => $medicine->id, 'medication_name' => $medicine->name, 'dose' => '1 tablet', 'frequency' => 'OD', 'duration_value' => 2, 'duration_unit' => 'days', 'quantity' => 2,
        ]]], $admin);
        $this->prepareEncounterForCompletion($encounter, $admin);
        $completed = app(ClinicalEncounterService::class)->completeEncounter($encounter->refresh(), $admin);

        foreach ([
            fn () => app(ClinicalEncounterService::class)->saveDraft($completed, ['treatment_plan' => 'Changed plan'], $admin),
            fn () => app(ClinicalEncounterService::class)->addDiagnosis($completed, ['diagnosis_type' => 'final', 'diagnosis_name' => 'Late diagnosis', 'certainty' => 'confirmed'], $admin),
            fn () => app(PrescriptionService::class)->updateItem($prescription->items()->firstOrFail(), ['medication_name' => 'Changed', 'dose' => '2 tablets', 'frequency' => 'OD', 'duration_value' => 2, 'duration_unit' => 'days'], $admin),
        ] as $mutation) {
            try {
                $mutation();
                $this->fail('A completed consultation mutation was accepted.');
            } catch (ValidationException $exception) {
                $this->assertStringContainsString('tayari imekamilika', collect($exception->errors())->flatten()->first());
            }
        }
    }

    public function test_opd_consultation_does_not_render_a_separate_sign_off_action(): void
    {
        $admin = $this->bootstrappedFacility();
        $doctor = $this->staffUser('doctor');
        $visit = $this->opdVisit($admin, VisitStatus::InProgress);

        Livewire::actingAs($doctor)
            ->test(OpdConsultation::class, ['visit' => $visit])
            ->assertSee('Save Draft')
            ->assertSee('Complete Consultation')
            ->assertSee('Print Summary')
            ->assertDontSee('Sign Off');
    }

    public function test_complete_consultation_removes_patient_from_active_opd_queue(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->opdVisit($admin, VisitStatus::InProgress);
        $encounter = app(ClinicalEncounterService::class)->startEncounter($visit, $admin);
        foreach (['waiting', 'called'] as $index => $status) {
            PatientQueue::query()->create([
                'facility_id' => currentFacility()->id,
                'visit_id' => $visit->id,
                'patient_id' => $visit->patient_id,
                'department_id' => $encounter->department_id,
                'queue_number' => 'OPD-ACTIVE-'.$index,
                'queue_date' => today(),
                'queue_status' => $status,
                'priority' => 'normal',
                'position' => $index + 2,
                'checked_in_at' => now(),
                'called_at' => $status === 'called' ? now() : null,
                'created_by' => $admin->id,
            ]);
        }
        $this->prepareEncounterForCompletion($encounter, $admin);

        app(ClinicalEncounterService::class)->completeEncounter($encounter->refresh(), $admin);

        $this->assertDatabaseMissing('patient_queues', [
            'visit_id' => $visit->id,
            'department_id' => $encounter->department_id,
            'queue_status' => 'serving',
        ]);
        $this->assertDatabaseHas('patient_queues', [
            'visit_id' => $visit->id,
            'department_id' => $encounter->department_id,
            'queue_status' => 'completed',
        ]);
        $this->assertSame(0, PatientQueue::query()
            ->where('visit_id', $visit->id)
            ->where('department_id', $encounter->department_id)
            ->whereIn('queue_status', ['waiting', 'called', 'serving'])
            ->count());
    }

    public function test_completion_creates_pharmacy_destination_for_prescriptions(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->opdVisit($admin, VisitStatus::InProgress);
        $encounter = app(ClinicalEncounterService::class)->startEncounter($visit, $admin);
        $medicine = $this->medicine($admin);
        app(ClinicalEncounterService::class)->addPrescription($encounter, [
            'items' => [[
                'medicine_id' => $medicine->id,
                'medication_name' => 'Paracetamol',
                'dose' => '500mg',
                'frequency' => 'TDS',
                'duration_value' => 3,
                'duration_unit' => 'days',
                'quantity' => 9,
            ]],
        ], $admin);
        $this->prepareEncounterForCompletion($encounter, $admin);

        app(ClinicalEncounterService::class)->completeEncounter($encounter->refresh(), $admin);

        $pharmacy = Department::query()->forCurrentFacility()->where('code', 'PHA')->firstOrFail();
        $this->assertDatabaseHas('patient_queues', [
            'visit_id' => $visit->id,
            'department_id' => $pharmacy->id,
            'queue_status' => 'waiting',
        ]);
        $this->assertSame(1, PatientQueue::query()
            ->where('visit_id', $visit->id)
            ->where('department_id', $pharmacy->id)
            ->whereIn('queue_status', ['waiting', 'called', 'serving'])
            ->count());
        $prescription = Prescription::query()->where('visit_id', $visit->id)->firstOrFail();
        $this->assertSame('prescribed', $prescription->status->value);
        Livewire::actingAs($admin)->test(PharmacyQueue::class)->assertSee($prescription->prescription_number);
        $this->assertSame(VisitStatus::AwaitingPharmacy, $visit->refresh()->visit_status);

        try {
            app(ClinicalEncounterService::class)->completeEncounter($encounter->refresh(), $admin);
        } catch (ValidationException) {
            // A completed consultation is immutable; idempotency is asserted by the queue count below.
        }
        $this->assertSame(1, PatientQueue::query()
            ->where('visit_id', $visit->id)
            ->where('department_id', $pharmacy->id)
            ->whereIn('queue_status', ['waiting', 'called', 'serving'])
            ->count());
    }

    public function test_cash_medicine_is_billed_and_pharmacy_queue_waits_for_full_medicine_payment(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->opdVisit($admin, VisitStatus::InProgress);
        $encounter = app(ClinicalEncounterService::class)->startEncounter($visit, $admin);
        $medicine = $this->medicine($admin);
        $billable = $this->service('Billable Paracetamol', 'MED-BILL-'.fake()->unique()->numerify('###'), 'medicine', $admin);
        $medicine->update(['service_id' => $billable->id]);
        $prescription = app(ClinicalEncounterService::class)->addPrescription($encounter, ['items' => [[
            'medicine_id' => $medicine->id,
            'medication_name' => $medicine->name,
            'dose' => '1 tablet',
            'frequency' => 'TDS',
            'duration_value' => 3,
            'duration_unit' => 'days',
            'quantity' => 9,
        ]]], $admin);
        $this->prepareEncounterForCompletion($encounter, $admin);

        app(ClinicalEncounterService::class)->completeEncounter($encounter->refresh(), $admin);

        $item = $prescription->items()->firstOrFail()->refresh();
        $this->assertSame('awaiting_payment', $prescription->refresh()->status->value);
        $this->assertNotNull($item->invoice_item_id);
        $this->assertSame(9000.0, (float) $item->invoiceItem->patient_amount);
        $this->assertSame(0, PatientQueue::query()->where('visit_id', $visit->id)->whereHas('department', fn ($query) => $query->where('code', 'PHA'))->whereIn('queue_status', ['waiting', 'called', 'serving'])->count());
        $this->assertSame(VisitStatus::AwaitingPayment, $visit->refresh()->visit_status);

        $unrelatedService = $this->service('Unrelated Outstanding Service', 'OTHER-'.fake()->unique()->numerify('###'), 'consultation', $admin);
        app(BillingChargeService::class)->addServiceCharge($visit->invoice->refresh(), $unrelatedService, $admin);

        $method = PaymentMethod::query()->create(['facility_id' => currentFacility()->id, 'name' => 'Cash', 'code' => 'CASH-MED', 'type' => 'cash', 'is_cash' => true, 'is_active' => true]);
        app(PaymentConfirmationService::class)->confirmPayment($visit->invoice->refresh(), $method, 4000, $admin, ['idempotency_key' => (string) Str::uuid()]);
        $this->assertSame('awaiting_payment', $prescription->refresh()->status->value);
        $this->assertSame(0, PatientQueue::query()->where('visit_id', $visit->id)->whereHas('department', fn ($query) => $query->where('code', 'PHA'))->whereIn('queue_status', ['waiting', 'called', 'serving'])->count());

        $finalPaymentKey = (string) Str::uuid();
        $finalPayment = app(PaymentConfirmationService::class)->confirmPayment($visit->invoice->refresh(), $method, 5000, $admin, ['idempotency_key' => $finalPaymentKey]);
        $retry = app(PaymentConfirmationService::class)->confirmPayment($visit->invoice->refresh(), $method, 5000, $admin, ['idempotency_key' => $finalPaymentKey]);
        $this->assertSame('prescribed', $prescription->refresh()->status->value);
        $this->assertSame(1, PatientQueue::query()->where('visit_id', $visit->id)->whereHas('department', fn ($query) => $query->where('code', 'PHA'))->whereIn('queue_status', ['waiting', 'called', 'serving'])->count());
        // The medicine stream is released independently; the unrelated charge still owns the visit-level payment state.
        $this->assertSame(VisitStatus::AwaitingPayment, $visit->refresh()->visit_status);
        $this->assertSame(1000.0, (float) $visit->invoice->refresh()->balance_amount);
        $this->assertSame($finalPayment->id, $retry->id);
        $this->assertSame(2, $visit->invoice->payments()->count());
    }

    public function test_insurance_medicine_coverage_copay_and_authorization_control_pharmacy_release(): void
    {
        $admin = $this->bootstrappedFacility();
        $provider = InsuranceProvider::query()->create(['facility_id' => currentFacility()->id, 'name' => 'Test Insurer', 'code' => 'INS-MED', 'provider_type' => 'private_insurance', 'claim_submission_method' => 'manual_report', 'is_active' => true]);

        foreach ([
            ['coverage' => 100, 'authorization' => false, 'expected' => 'prescribed', 'patient' => 0],
            ['coverage' => 80, 'authorization' => false, 'expected' => 'awaiting_payment', 'patient' => 1800],
            ['coverage' => 100, 'authorization' => true, 'expected' => 'awaiting_payment', 'patient' => 0],
        ] as $index => $case) {
            $visit = $this->opdVisit($admin, VisitStatus::InProgress);
            $profile = PatientPayerProfile::query()->create(['facility_id' => currentFacility()->id, 'patient_id' => $visit->patient_id, 'payer_type' => 'insurance', 'insurance_provider_id' => $provider->id, 'membership_number' => 'MEM-'.$index, 'coverage_status' => 'active', 'is_primary' => true, 'created_by' => $admin->id]);
            $visit->update(['payer_type' => 'insurance', 'patient_payer_profile_id' => $profile->id]);
            $membership = PatientInsuranceMembership::query()->create(['facility_id' => currentFacility()->id, 'patient_id' => $visit->patient_id, 'insurance_provider_id' => $provider->id, 'membership_number' => 'MEM-'.$index, 'membership_type' => 'principal', 'verification_status' => 'verified', 'is_primary' => true, 'is_active' => true, 'created_by' => $admin->id]);
            $encounter = app(ClinicalEncounterService::class)->startEncounter($visit->refresh(), $admin);
            $medicine = $this->medicine($admin);
            $service = $this->service('Insurance Medicine '.$index, 'INS-MED-'.$index, 'medicine', $admin);
            $service->prices()->where('payer_type', 'insurance')->update(['insurance_provider_id' => $provider->id]);
            $medicine->update(['service_id' => $service->id]);
            InsuranceCoverageRule::query()->create(['facility_id' => currentFacility()->id, 'insurance_provider_id' => $provider->id, 'rule_scope' => 'medicine', 'medicine_id' => $medicine->id, 'coverage_status' => $case['authorization'] ? 'authorization_required' : ($case['coverage'] < 100 ? 'partially_covered' : 'covered'), 'coverage_percentage' => $case['coverage'], 'requires_pre_authorization' => $case['authorization'], 'priority' => 100, 'is_active' => true]);
            $prescription = app(ClinicalEncounterService::class)->addPrescription($encounter, ['items' => [[
                'medicine_id' => $medicine->id, 'medication_name' => $medicine->name, 'dose' => '1 tablet', 'frequency' => 'TDS', 'duration_value' => 3, 'duration_unit' => 'days', 'quantity' => 9,
            ]]], $admin);
            $this->prepareEncounterForCompletion($encounter, $admin);
            app(ClinicalEncounterService::class)->completeEncounter($encounter->refresh(), $admin);

            $invoiceItem = $prescription->items()->firstOrFail()->invoiceItem;
            $this->assertSame($case['expected'], $prescription->refresh()->status->value);
            $this->assertSame((float) $case['patient'], (float) $invoiceItem->patient_amount);
            $this->assertSame($membership->id, $invoiceItem->patient_insurance_membership_id);
            $this->assertSame($case['expected'] === 'prescribed' ? 1 : 0, PatientQueue::query()->where('visit_id', $visit->id)->whereHas('department', fn ($query) => $query->where('code', 'PHA'))->whereIn('queue_status', ['waiting', 'called', 'serving'])->count());
            if ($case['coverage'] === 100 && ! $case['authorization']) {
                $this->assertSame('covered', $visit->invoice->refresh()->payment_status);
                $this->assertSame('covered_by_insurance', $visit->invoice->invoice_status->value);
            }
        }
    }

    public function test_medicine_payment_never_reopens_a_referred_visit(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->opdVisit($admin, VisitStatus::InProgress);
        $encounter = app(ClinicalEncounterService::class)->startEncounter($visit, $admin);
        $medicine = $this->medicine($admin);
        $billable = $this->service('Referral Medicine', 'REF-MED', 'medicine', $admin);
        $medicine->update(['service_id' => $billable->id]);
        $prescription = app(ClinicalEncounterService::class)->addPrescription($encounter, ['items' => [[
            'medicine_id' => $medicine->id, 'medication_name' => $medicine->name, 'dose' => '1 tablet', 'frequency' => 'OD', 'duration_value' => 1, 'duration_unit' => 'days', 'quantity' => 1,
        ]]], $admin);
        app(ClinicalEncounterService::class)->createReferral($encounter, ['destination_facility_name' => 'Regional Hospital', 'reason' => 'Specialist care', 'urgency' => 'urgent'], $admin);
        app(ClinicalEncounterService::class)->completeEncounter($encounter->refresh(), $admin, ['clinical_summary' => 'Referred for specialist care', 'outcome' => 'referred']);
        $this->assertSame(VisitStatus::Referred, $visit->refresh()->visit_status);

        $method = PaymentMethod::query()->create(['facility_id' => currentFacility()->id, 'name' => 'Cash', 'code' => 'CASH-REF', 'type' => 'cash', 'is_cash' => true, 'is_active' => true]);
        app(PaymentConfirmationService::class)->confirmPayment($visit->invoice->refresh(), $method, 1000, $admin, ['idempotency_key' => (string) Str::uuid()]);

        $this->assertSame('prescribed', $prescription->refresh()->status->value);
        $this->assertSame(VisitStatus::Referred, $visit->refresh()->visit_status);
        $this->assertSame(0, PatientQueue::query()->where('visit_id', $visit->id)->whereHas('department', fn ($query) => $query->where('code', 'PHA'))->whereIn('queue_status', ['waiting', 'called', 'serving'])->count());
    }

    public function test_prescription_billing_reconciliation_is_dry_run_and_reports_ambiguous_quantities(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->opdVisit($admin, VisitStatus::InProgress);
        $encounter = app(ClinicalEncounterService::class)->startEncounter($visit, $admin);
        $medicine = $this->medicine($admin);
        $service = $this->service('Legacy Medicine', 'LEGACY-MED', 'medicine', $admin);
        $medicine->update(['service_id' => $service->id]);
        $prescription = app(ClinicalEncounterService::class)->addPrescription($encounter, ['items' => [[
            'medicine_id' => $medicine->id, 'medication_name' => $medicine->name, 'dose' => '1 tablet', 'frequency' => 'OD', 'duration_value' => 1, 'duration_unit' => 'days', 'quantity' => 1,
        ]]], $admin);
        $this->prepareEncounterForCompletion($encounter, $admin);
        app(ClinicalEncounterService::class)->completeEncounter($encounter->refresh(), $admin);

        $item = $prescription->items()->firstOrFail();
        $invoiceItemId = $item->invoice_item_id;
        $item->update(['invoice_item_id' => null, 'quantity' => null]);

        $this->assertSame(0, Artisan::call('pharmacy:reconcile-prescription-billing', ['--ids' => [$prescription->id]]));
        $output = Artisan::output();
        $this->assertStringContainsString('invalid_quantity', $output);
        $this->assertStringContainsString('classification=safe_reconstructable_quantity', $output);
        $this->assertStringContainsString('existing_charge_missing_link', $output);
        $this->assertStringContainsString("invoice_item={$invoiceItemId}", $output);

        $this->assertNull($item->refresh()->invoice_item_id);
        $this->assertNull($item->quantity);
        $this->assertDatabaseHas('invoice_items', ['id' => $invoiceItemId]);
    }

    public function test_completion_waits_for_laboratory_results_and_does_not_route_back_after_release(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->opdVisit($admin, VisitStatus::InProgress);
        $encounter = app(ClinicalEncounterService::class)->startEncounter($visit, $admin);
        $labService = $this->service('Malaria MRDT', 'LAB-COMPLETE', 'laboratory_test', $admin);
        $labService->prices()->update(['amount' => 0]);
        app(ClinicalEncounterService::class)->addLabOrder($encounter, [
            'service_ids' => [$labService->id],
            'clinical_notes' => 'Exclude malaria',
        ], $admin);
        $this->prepareEncounterForCompletion($encounter, $admin);

        try {
            app(ClinicalEncounterService::class)->completeEncounter($encounter->refresh(), $admin);
            $this->fail('Consultation completed while laboratory work was pending.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('laboratory', $exception->errors());
        }

        $laboratory = Department::query()->forCurrentFacility()->where('code', 'LAB')->firstOrFail();
        $order = $encounter->laboratoryOrders()->firstOrFail();
        $order->items()->update(['status' => 'completed', 'result_status' => 'released']);
        $order->update(['status' => 'completed', 'payment_status' => 'paid', 'completed_at' => now()]);
        $visit->invoice()->update(['balance_amount' => 0, 'payment_status' => 'paid', 'invoice_status' => 'paid']);
        app(VisitClosureService::class)->completeDepartmentQueues($visit, 'LAB', $admin);
        app(ClinicalEncounterService::class)->completeEncounter($encounter->refresh(), $admin);

        $this->assertSame('completed', $encounter->refresh()->status->value);
        $this->assertSame(0, PatientQueue::query()->where('visit_id', $visit->id)->where('department_id', $laboratory->id)->whereIn('queue_status', ['waiting', 'called', 'serving'])->count());
    }

    public function test_pending_laboratory_work_blocks_pharmacy_routing(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->opdVisit($admin, VisitStatus::InProgress);
        $encounter = app(ClinicalEncounterService::class)->startEncounter($visit, $admin);
        $labService = $this->service('Parallel Malaria Test', 'LAB-PARALLEL', 'laboratory_test', $admin);
        app(ClinicalEncounterService::class)->addLabOrder($encounter, ['service_ids' => [$labService->id]], $admin);
        $medicine = $this->medicine($admin);
        app(ClinicalEncounterService::class)->addPrescription($encounter, [
            'items' => [[
                'medicine_id' => $medicine->id,
                'medication_name' => 'Paracetamol',
                'dose' => '500mg',
                'frequency' => 'TDS',
                'duration_value' => 3,
                'duration_unit' => 'days',
                'quantity' => 9,
            ]],
        ], $admin);
        $this->prepareEncounterForCompletion($encounter, $admin);

        try {
            app(ClinicalEncounterService::class)->completeEncounter($encounter->refresh(), $admin);
            $this->fail('Consultation completed while laboratory work was pending.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('laboratory', $exception->errors());
        }

        $queues = PatientQueue::query()
            ->where('visit_id', $visit->id)
            ->whereIn('queue_status', ['waiting', 'called', 'serving'])
            ->whereHas('department', fn ($query) => $query->whereIn('code', ['LAB', 'PHA']))
            ->with('department')
            ->get();
        $this->assertNotContains('PHA', $queues->pluck('department.code')->all());
        $this->assertNull($encounter->refresh()->completed_at);
        $this->assertDatabaseMissing('visit_movements', [
            'visit_id' => $visit->id,
            'movement_type' => 'queue_created',
            'reason' => 'Pharmacy destination created after consultation',
        ]);
    }

    public function test_completion_card_uses_next_destinations_and_user_friendly_order_statuses(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->opdVisit($admin, VisitStatus::InProgress);
        $encounter = app(ClinicalEncounterService::class)->startEncounter($visit, $admin);
        $labService = $this->service('Malaria Test', 'LAB-STATUS', 'laboratory_test', $admin);
        $labOrder = app(ClinicalEncounterService::class)->addLabOrder($encounter, ['service_ids' => [$labService->id]], $admin);
        $labOrder->items()->update(['result_status' => 'verified']);
        $medicine = $this->medicine($admin);
        app(ClinicalEncounterService::class)->addPrescription($encounter, [
            'items' => [[
                'medicine_id' => $medicine->id,
                'medication_name' => 'Paracetamol',
                'strength' => '500 mg',
                'dose' => '500mg',
                'frequency' => 'TDS',
                'duration_value' => 3,
                'duration_unit' => 'days',
            ]],
        ], $admin);

        Livewire::actingAs($admin)
            ->test(OpdConsultation::class, ['visit' => $visit])
            ->set('form.outcome', 'admitted_bed_rest')
            ->assertSee('Next Destinations')
            ->assertSee('Draft — Pending Consultation Completion')
            ->assertSee('Verified')
            ->assertDontSee('Admission —')
            ->assertDontSee('Referral —')
            ->assertSee('Completing...');
    }

    public function test_admit_creates_bed_queue_preserves_orders_and_does_not_complete_visit(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->opdVisit($admin, VisitStatus::InProgress);
        $encounter = app(ClinicalEncounterService::class)->startEncounter($visit, $admin);
        $medicine = $this->medicine($admin);
        app(ClinicalEncounterService::class)->addPrescription($encounter, [
            'items' => [[
                'medicine_id' => $medicine->id,
                'medication_name' => 'Paracetamol',
                'dose' => '500mg',
                'frequency' => 'TDS',
                'duration_value' => 3,
                'duration_unit' => 'days',
                'quantity' => 9,
            ]],
        ], $admin);

        $completed = app(ClinicalEncounterService::class)->completeEncounter($encounter, $admin, [
            'clinical_summary' => 'Patient requires admission',
            'outcome' => 'admitted_bed_rest',
        ]);

        $bed = Department::query()->forCurrentFacility()->where('code', 'BED')->firstOrFail();
        $this->assertSame('completed', $completed->status->value);
        $this->assertDatabaseHas('patient_queues', ['visit_id' => $visit->id, 'department_id' => $bed->id, 'queue_status' => 'waiting']);
        $this->assertDatabaseHas('prescriptions', ['clinical_encounter_id' => $encounter->id, 'status' => 'prescribed']);
        $this->assertSame(VisitStatus::AwaitingBed, $visit->refresh()->visit_status);
        $this->assertNull($visit->completed_at);
    }

    public function test_observation_creates_bed_queue_and_uses_observation_visit_state(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->opdVisit($admin, VisitStatus::InProgress);
        $encounter = app(ClinicalEncounterService::class)->startEncounter($visit, $admin);

        app(ClinicalEncounterService::class)->completeEncounter($encounter, $admin, [
            'clinical_summary' => 'Short observation required',
            'outcome' => 'observation',
        ]);

        $bed = Department::query()->forCurrentFacility()->where('code', 'BED')->firstOrFail();
        $this->assertDatabaseHas('patient_queues', ['visit_id' => $visit->id, 'department_id' => $bed->id, 'queue_status' => 'waiting']);
        $this->assertSame(VisitStatus::UnderObservation, $visit->refresh()->visit_status);
        $this->assertNull($visit->completed_at);
    }

    public function test_follow_up_outcome_creates_future_appointment_without_same_day_opd_queue(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->opdVisit($admin, VisitStatus::InProgress);
        $encounter = app(ClinicalEncounterService::class)->startEncounter($visit, $admin);
        $followUpDate = now()->addDays(7)->toDateString();

        app(ClinicalEncounterService::class)->completeEncounter($encounter, $admin, [
            'clinical_summary' => 'Review response to treatment',
            'outcome' => 'follow_up',
            'follow_up_date' => $followUpDate,
            'follow_up_reason' => 'Clinical review',
            'follow_up_department_id' => $encounter->department_id,
        ]);

        $this->assertDatabaseHas('appointments', [
            'clinical_encounter_id' => $encounter->id,
            'reason' => 'Clinical review',
            'status' => 'booked',
        ]);
        $this->assertTrue($encounter->appointments()->whereDate('appointment_date', $followUpDate)->exists());
        $this->assertSame(0, PatientQueue::query()
            ->where('visit_id', $visit->id)
            ->where('department_id', $encounter->department_id)
            ->whereIn('queue_status', ['waiting', 'called', 'serving'])
            ->count());
        $this->assertSame(VisitStatus::Completed, $visit->refresh()->visit_status);
    }

    public function test_completed_downstream_orders_do_not_create_new_queues(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->opdVisit($admin, VisitStatus::InProgress);
        $encounter = app(ClinicalEncounterService::class)->startEncounter($visit, $admin);
        $labService = $this->service('Completed Lab Test', 'LAB-DONE', 'laboratory_test', $admin);
        $labService->prices()->update(['amount' => 0]);
        $labOrder = app(ClinicalEncounterService::class)->addLabOrder($encounter, ['service_ids' => [$labService->id]], $admin);
        $labOrder->items()->update(['status' => 'completed', 'result_status' => 'released']);
        $labOrder->update(['status' => 'completed', 'payment_status' => 'paid', 'completed_at' => now()]);
        PatientQueue::query()->where('visit_id', $visit->id)->whereHas('department', fn ($query) => $query->where('code', 'LAB'))->update(['queue_status' => 'completed', 'service_completed_at' => now()]);
        $visit->invoice()->update(['balance_amount' => 0, 'payment_status' => 'paid', 'invoice_status' => 'paid']);
        $medicine = $this->medicine($admin);
        $prescription = app(ClinicalEncounterService::class)->addPrescription($encounter, [
            'items' => [[
                'medicine_id' => $medicine->id,
                'medication_name' => 'Completed medicine',
                'dose' => '1',
                'frequency' => 'OD',
                'duration_value' => 1,
                'duration_unit' => 'days',
            ]],
        ], $admin);
        $prescription->update(['status' => 'dispensed', 'dispensed_at' => now()]);

        app(ClinicalEncounterService::class)->completeEncounter($encounter, $admin, [
            'clinical_summary' => 'All ordered work is complete',
            'outcome' => 'discharged_home',
        ]);

        $this->assertSame(0, PatientQueue::query()
            ->where('visit_id', $visit->id)
            ->whereHas('department', fn ($query) => $query->whereIn('code', ['LAB', 'PHA']))
            ->whereIn('queue_status', ['waiting', 'called', 'serving'])
            ->count());
        $this->assertSame(VisitStatus::Completed, $visit->refresh()->visit_status);
    }

    public function test_discharge_conflicting_with_active_admission_is_rejected(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->opdVisit($admin, VisitStatus::InProgress);
        $encounter = app(ClinicalEncounterService::class)->startEncounter($visit, $admin);
        ObservationAdmission::factory()->create([
            'facility_id' => $encounter->facility_id,
            'patient_id' => $encounter->patient_id,
            'visit_id' => $encounter->visit_id,
            'clinical_encounter_id' => $encounter->id,
            'admitted_by' => $admin->id,
            'created_by' => $admin->id,
            'status' => 'awaiting_bed',
        ]);

        try {
            app(ClinicalEncounterService::class)->completeEncounter($encounter, $admin, [
                'clinical_summary' => 'Contradictory discharge',
                'outcome' => 'discharged_home',
            ]);
            $this->fail('Discharge completed despite an active admission.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'The selected final outcome conflicts with the current admission order.',
                $exception->errors()['outcome'][0],
            );
        }

        $this->assertNull($encounter->refresh()->completed_at);
        $this->assertNull($encounter->signed_off_at);
    }

    public function test_released_laboratory_work_then_pharmacy_work_closes_visit_last(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->opdVisit($admin, VisitStatus::InProgress);
        $encounter = app(ClinicalEncounterService::class)->startEncounter($visit, $admin);
        $labService = $this->service('Parallel Closure Test', 'LAB-CLOSE', 'laboratory_test', $admin);
        $labService->prices()->update(['amount' => 0]);
        $order = app(ClinicalEncounterService::class)->addLabOrder($encounter, ['service_ids' => [$labService->id]], $admin);
        $order->items()->update(['status' => 'completed', 'result_status' => 'released']);
        $order->update(['status' => 'completed', 'payment_status' => 'paid', 'completed_at' => now()]);
        $visit->invoice()->update(['balance_amount' => 0, 'payment_status' => 'paid', 'invoice_status' => 'paid']);
        $medicine = $this->medicine($admin);
        $prescription = app(ClinicalEncounterService::class)->addPrescription($encounter, [
            'items' => [[
                'medicine_id' => $medicine->id,
                'medication_name' => 'Paracetamol',
                'dose' => '500mg',
                'frequency' => 'TDS',
                'duration_value' => 3,
                'duration_unit' => 'days',
                'quantity' => 9,
            ]],
        ], $admin);
        $this->prepareEncounterForCompletion($encounter, $admin);
        app(ClinicalEncounterService::class)->completeEncounter($encounter->refresh(), $admin);

        $closure = app(VisitClosureService::class);
        $closure->completeDepartmentQueues($visit, 'LAB', $admin);
        $closure->evaluate($visit->refresh(), $admin);

        $this->assertDatabaseHas('patient_queues', [
            'visit_id' => $visit->id,
            'department_id' => Department::query()->forCurrentFacility()->where('code', 'PHA')->value('id'),
            'queue_status' => 'waiting',
        ]);
        $this->assertSame(VisitStatus::AwaitingPharmacy, $visit->refresh()->visit_status);

        $prescription->items()->update(['status' => 'dispensed', 'remaining_quantity' => 0]);
        $prescription->update(['status' => 'dispensed', 'dispensed_at' => now()]);
        $closure->completeDepartmentQueues($visit, 'PHA', $admin);
        $closure->evaluate($visit->refresh(), $admin);

        $this->assertSame(VisitStatus::Completed, $visit->refresh()->visit_status);
        $this->assertDatabaseHas('patient_queues', [
            'visit_id' => $visit->id,
            'department_id' => Department::query()->forCurrentFacility()->where('code', 'LAB')->value('id'),
            'queue_status' => 'completed',
        ]);
    }

    public function test_missing_required_destination_configuration_rolls_back_completion(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->opdVisit($admin, VisitStatus::InProgress);
        $encounter = app(ClinicalEncounterService::class)->startEncounter($visit, $admin);
        $medicine = $this->medicine($admin);
        app(ClinicalEncounterService::class)->addPrescription($encounter, [
            'items' => [[
                'medicine_id' => $medicine->id,
                'medication_name' => 'Paracetamol',
                'dose' => '500mg',
                'frequency' => 'TDS',
                'duration_value' => 3,
                'duration_unit' => 'days',
                'quantity' => 9,
            ]],
        ], $admin);
        $this->prepareEncounterForCompletion($encounter, $admin);
        Department::query()->forCurrentFacility()->where('code', 'PHA')->update(['is_active' => false]);

        try {
            app(ClinicalEncounterService::class)->completeEncounter($encounter->refresh(), $admin, [
                'clinical_summary' => 'This pending change must roll back',
                'treatment_plan' => 'Continue ordered care',
                'outcome' => 'discharged_home',
            ]);
            $this->fail('Completion succeeded without a configured Pharmacy destination.');
        } catch (ValidationException $exception) {
            $this->assertSame('Pharmacy is not configured correctly.', $exception->errors()['destination'][0]);
        }

        $this->assertSame('in_progress', $encounter->refresh()->status->value);
        $this->assertSame('Patient is clinically stable', $encounter->clinical_summary);
        $this->assertNull($encounter->signed_off_at);
        $this->assertNull($encounter->completed_at);
        $this->assertDatabaseHas('patient_queues', [
            'visit_id' => $visit->id,
            'department_id' => $encounter->department_id,
            'queue_status' => 'serving',
        ]);
    }

    public function test_pending_laboratory_work_rolls_back_completion_even_if_lab_queue_is_disabled(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->opdVisit($admin, VisitStatus::InProgress);
        $encounter = app(ClinicalEncounterService::class)->startEncounter($visit, $admin);
        $labService = $this->service('Missing Lab Destination Test', 'LAB-MISSING', 'laboratory_test', $admin);
        app(ClinicalEncounterService::class)->addLabOrder($encounter, ['service_ids' => [$labService->id]], $admin);
        $this->prepareEncounterForCompletion($encounter, $admin);
        Department::query()->forCurrentFacility()->where('code', 'LAB')->update(['queue_enabled' => false]);

        try {
            app(ClinicalEncounterService::class)->completeEncounter($encounter->refresh(), $admin);
            $this->fail('Completion succeeded while laboratory work was pending.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Consultation cannot be completed because some laboratory orders are not yet verified and released.',
                $exception->errors()['laboratory'][0],
            );
        }

        $this->assertSame('in_progress', $encounter->refresh()->status->value);
        $this->assertNull($encounter->signed_off_at);
        $this->assertNull($encounter->completed_at);
    }

    public function test_existing_signed_encounter_completes_without_re_signing(): void
    {
        $admin = $this->bootstrappedFacility();
        $encounter = app(ClinicalEncounterService::class)->startEncounter(
            $this->opdVisit($admin, VisitStatus::InProgress),
            $admin,
        );
        app(ClinicalEncounterService::class)->saveDraft($encounter, [
            'clinical_summary' => 'Signed summary',
            'outcome' => 'discharged_home',
        ], $admin);
        app(DiagnosisService::class)->addDiagnosis($encounter->refresh(), [
            'diagnosis_type' => 'final',
            'diagnosis_name' => 'Acute illness',
            'certainty' => 'confirmed',
            'is_primary' => true,
        ], $admin);
        $service = app(ClinicalEncounterService::class);
        $service->signOff($encounter->refresh(), $admin);

        $encounter->refresh();
        $previousSigner = $encounter->signed_off_by;
        $previousSignedAt = $encounter->signed_off_at?->toISOString();
        $completed = $service->completeEncounter($encounter->refresh(), $admin);

        $this->assertSame('completed', $completed->status->value);
        $this->assertSame($previousSigner, $completed->signed_off_by);
        $this->assertSame($previousSignedAt, $completed->signed_off_at?->toISOString());
        $this->assertSame($admin->id, $completed->completed_by);
    }

    public function test_legacy_repair_fixes_only_unambiguous_records_and_reports_ambiguous_queues(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->opdVisit($admin, VisitStatus::InProgress);
        $encounter = app(ClinicalEncounterService::class)->startEncounter($visit, $admin);
        $this->prepareEncounterForCompletion($encounter, $admin);
        app(ClinicalEncounterService::class)->completeEncounter($encounter->refresh(), $admin);
        $encounter->update(['status' => 'awaiting_results']);

        $opd = Department::query()->forCurrentFacility()->where('code', 'OPD')->firstOrFail();
        PatientQueue::query()->create([
            'facility_id' => currentFacility()->id,
            'visit_id' => $visit->id,
            'patient_id' => $visit->patient_id,
            'department_id' => $opd->id,
            'queue_number' => 'OPD-LEGACY-001',
            'queue_date' => today(),
            'queue_status' => 'waiting',
            'priority' => 'normal',
            'position' => 1,
            'checked_in_at' => now(),
            'created_by' => $admin->id,
        ]);
        $pharmacy = Department::query()->forCurrentFacility()->where('code', 'PHA')->firstOrFail();
        foreach (['PHA-AMB-001', 'PHA-AMB-002'] as $number) {
            PatientQueue::query()->create([
                'facility_id' => currentFacility()->id,
                'visit_id' => $visit->id,
                'patient_id' => $visit->patient_id,
                'department_id' => $pharmacy->id,
                'queue_number' => $number,
                'queue_date' => today(),
                'queue_status' => 'waiting',
                'priority' => 'normal',
                'position' => 1,
                'checked_in_at' => now(),
                'created_by' => $admin->id,
            ]);
        }

        $this->artisan('workflow:repair-post-consultation')
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();
        $this->assertSame('awaiting_results', $encounter->refresh()->status->value);

        $this->artisan('workflow:repair-post-consultation', ['--apply' => true])
            ->expectsOutputToContain('Ambiguous duplicate active queues')
            ->assertSuccessful();

        $this->assertSame('completed', $encounter->refresh()->status->value);
        $this->assertDatabaseHas('patient_queues', [
            'queue_number' => 'OPD-LEGACY-001',
            'queue_status' => 'completed',
        ]);
        $this->assertSame(2, PatientQueue::query()
            ->whereIn('queue_number', ['PHA-AMB-001', 'PHA-AMB-002'])
            ->where('queue_status', 'waiting')
            ->count());
        $this->assertDatabaseHas('activity_logs', [
            'event' => 'post_consultation_legacy_repaired',
            'subject_id' => $encounter->id,
        ]);
    }

    public function test_user_without_clinician_completion_permission_cannot_sign_off_or_complete(): void
    {
        $admin = $this->bootstrappedFacility();
        $unauthorized = User::factory()->create();
        StaffProfile::factory()->create([
            'facility_id' => currentFacility()->id,
            'user_id' => $unauthorized->id,
        ]);
        $encounter = app(ClinicalEncounterService::class)->startEncounter(
            $this->opdVisit($admin, VisitStatus::InProgress),
            $admin,
        );

        $failures = 0;
        foreach ([
            fn () => app(ClinicalEncounterService::class)->signOff($encounter, $unauthorized),
            fn () => app(ClinicalEncounterService::class)->completeEncounter($encounter, $unauthorized),
        ] as $action) {
            try {
                $action();
            } catch (AuthorizationException) {
                $failures++;
            }
        }

        $this->assertSame(2, $failures);
        $this->assertNull($encounter->refresh()->signed_off_at);
        $this->assertNull($encounter->completed_at);
    }

    public function test_cross_facility_clinician_cannot_complete_consultation(): void
    {
        $admin = $this->bootstrappedFacility();
        $encounter = app(ClinicalEncounterService::class)->startEncounter(
            $this->opdVisit($admin, VisitStatus::InProgress),
            $admin,
        );
        $otherFacility = Facility::factory()->create();
        $otherDoctor = $this->staffUser('doctor', $otherFacility);

        try {
            app(ClinicalEncounterService::class)->completeEncounter($encounter, $otherDoctor, [
                'clinical_summary' => 'Cross-facility completion must fail',
                'outcome' => 'discharged_home',
            ]);
            $this->fail('Cross-facility clinician completed the consultation.');
        } catch (AuthorizationException) {
            $this->assertNull($encounter->refresh()->completed_at);
            $this->assertNull($encounter->signed_off_at);
        }
    }

    public function test_completion_reports_validation_failures_and_does_not_redirect(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->opdVisit($admin, VisitStatus::InProgress);
        $encounter = app(ClinicalEncounterService::class)->startEncounter($visit, $admin);
        $component = Livewire::actingAs($admin)
            ->test(OpdConsultation::class, ['visit' => $visit])
            ->call('completeConsultation')
            ->assertHasErrors(['form.outcome', 'clinical_content'])
            ->assertNoRedirect();

        $component
            ->set('form.outcome', 'discharged_home')
            ->call('completeConsultation')
            ->assertHasErrors(['clinical_content'])
            ->assertNoRedirect();

        $this->assertDatabaseMissing('clinical_encounters', [
            'visit_id' => $visit->id,
            'status' => 'completed',
        ]);
    }

    public function test_two_clinicians_cannot_start_same_department_encounter(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->visit($admin, VisitStatus::InQueue);
        app(ClinicalEncounterService::class)->startEncounter($visit, $admin);

        $this->expectException(ValidationException::class);
        app(ClinicalEncounterService::class)->startEncounter($visit->refresh(), User::factory()->create());
    }

    public function test_completed_encounter_is_immutable_without_amend_permission(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->visit($admin, VisitStatus::InQueue);
        $service = app(ClinicalEncounterService::class);
        $encounter = $service->startEncounter($visit, $admin);
        $service->saveDraft($encounter, ['clinical_summary' => 'Summary', 'treatment_plan' => 'Plan', 'outcome' => 'discharged_home'], $admin);
        $service->addDiagnosis($encounter->refresh(), ['diagnosis_type' => 'final', 'diagnosis_name' => 'Fever', 'certainty' => 'confirmed'], $admin);
        $service->signOff($encounter->refresh(), $admin);
        $completed = $service->completeEncounter($encounter->refresh(), $admin);

        $user = User::factory()->create();
        $this->expectException(ValidationException::class);
        $service->saveDraft($completed, ['clinical_summary' => 'Changed'], $user);
    }

    public function test_diagnosis_primary_is_unique_and_icd_import_is_idempotent(): void
    {
        $admin = $this->bootstrappedFacility();
        $encounter = app(ClinicalEncounterService::class)->startEncounter($this->visit($admin, VisitStatus::InQueue), $admin);
        $service = app(DiagnosisService::class);
        $first = $service->addDiagnosis($encounter, ['diagnosis_type' => 'provisional', 'diagnosis_name' => 'Malaria', 'certainty' => 'probable', 'is_primary' => true], $admin);
        $second = $service->addDiagnosis($encounter, ['diagnosis_type' => 'final', 'diagnosis_name' => 'Fever', 'certainty' => 'confirmed', 'is_primary' => true], $admin);

        $this->assertFalse($first->refresh()->is_primary);
        $this->assertTrue($second->refresh()->is_primary);

        $path = tempnam(sys_get_temp_dir(), 'icd');
        file_put_contents($path, "code,title\nZ99,Test code\nZ99,Test code updated\n");
        Artisan::call('icd10:import', ['file' => $path]);
        Artisan::call('icd10:import', ['file' => $path]);
        $this->assertSame(1, Icd10Code::query()->where('code', 'Z99')->count());
    }

    public function test_lab_order_prescription_procedure_followup_and_referral_foundations_work(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->opdVisit($admin, VisitStatus::InProgress);
        $encounter = app(ClinicalEncounterService::class)->startEncounter($visit, $admin);
        $labService = $this->service('Malaria MRDT', 'LAB001', 'laboratory_test', $admin);
        $procedure = $this->service('Dressing', 'PROC001', 'procedure', $admin);
        $clinical = app(ClinicalEncounterService::class);

        $lab = $clinical->addLabOrder($encounter->refresh(), ['service_ids' => [$labService->id], 'clinical_notes' => 'Rule out malaria'], $admin);
        $medicine = $this->medicine($admin);
        $rx = $clinical->addPrescription($encounter->refresh(), ['items' => [['medicine_id' => $medicine->id, 'medication_name' => 'Paracetamol', 'dose' => '500mg', 'frequency' => 'TDS', 'duration_value' => 3, 'duration_unit' => 'days']]], $admin);
        $proc = $clinical->addProcedureOrder($encounter->refresh(), ['service_id' => $procedure->id, 'procedure_name_snapshot' => 'Dressing'], $admin);
        $appt = $clinical->createFollowUp($encounter->refresh(), ['scheduled_start' => now()->addDay()->format('Y-m-d H:i:s'), 'department_id' => $encounter->department_id], $admin);
        $ref = $clinical->createReferral($encounter->refresh(), ['destination_facility_name' => 'Regional Hospital', 'reason' => 'Specialist review', 'urgency' => 'urgent'], $admin);

        $this->assertStringStartsWith('LAB-', $lab->order_number);
        $this->assertStringStartsWith('RX-', $rx->prescription_number);
        $this->assertSame('Dressing', $proc->procedure_name_snapshot);
        $this->assertSame('booked', $appt->status->value);
        $this->assertStringStartsWith('REF-', $ref->referral_number);
    }

    public function test_routes_for_prints_dashboard_and_reports_render(): void
    {
        $admin = $this->bootstrappedFacility();
        $visit = $this->opdVisit($admin, VisitStatus::InProgress);
        $encounter = app(ClinicalEncounterService::class)->startEncounter($visit, $admin);

        $this->actingAs($admin)->get(route('opd.dashboard'))->assertOk();
        $this->actingAs($admin)->get(route('clinical-encounters.print', $encounter))->assertOk();
        Livewire::actingAs($admin)
            ->test(OpdConsultation::class, ['visit' => $visit])
            ->call('printSummary')
            ->assertRedirect(route('clinical-encounters.print', $encounter));
        $referral = app(ClinicalEncounterService::class)->createReferral($encounter, ['destination_facility_name' => 'Regional Hospital', 'reason' => 'Review', 'urgency' => 'routine'], $admin);
        $this->actingAs($admin)->get(route('referrals.print', $referral))->assertOk();
        $this->actingAs($admin)->get(route('reports.triage.export'))->assertOk();
        $this->actingAs($admin)->get(route('reports.opd.export'))->assertOk();
        $this->actingAs($admin)->get(route('reports.diagnoses.export'))->assertOk();
        $this->actingAs($admin)->get(route('reports.referrals.export'))->assertOk();
    }

    private function bootstrappedFacility(): User
    {
        $admin = User::factory()->superAdmin()->create(['email' => fake()->unique()->safeEmail()]);
        Facility::query()->create([
            'name' => 'James Medical Dispensary',
            'code' => 'JMD',
            'facility_type' => FacilityType::Dispensary,
            'ownership_type' => OwnershipType::Private,
            'phone_primary' => '+255700000000',
            'region' => 'Dar es Salaam',
            'district' => 'Kinondoni',
            'ward' => 'Kijitonyama',
            'physical_address' => 'Kijitonyama',
            'setup_completed_at' => now(),
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
        $this->seed([PermissionSeeder::class, DepartmentSeeder::class, MinimalIcd10Seeder::class, RoleSeeder::class, RolePermissionSeeder::class]);
        foreach (Permission::query()->pluck('name') as $permission) {
            $admin->givePermissionTo($permission);
        }

        return $admin;
    }

    private function prepareEncounterForCompletion(ClinicalEncounter $encounter, User $actor): void
    {
        $service = app(ClinicalEncounterService::class);
        $service->saveDraft($encounter, [
            'clinical_summary' => 'Patient is clinically stable',
            'treatment_plan' => 'Continue ordered care',
            'outcome' => 'discharged_home',
        ], $actor);
        $service->addDiagnosis($encounter->refresh(), [
            'diagnosis_type' => 'final',
            'diagnosis_name' => 'Acute illness',
            'certainty' => 'confirmed',
            'is_primary' => true,
        ], $actor);
    }

    private function staffUser(string $roleName, ?Facility $facility = null): User
    {
        $user = User::factory()->create();
        StaffProfile::factory()->create(['facility_id' => ($facility ?? currentFacility())->id, 'user_id' => $user->id]);
        $role = Role::query()->where('name', $roleName)->firstOrFail();
        $user->assignRole($role);

        return $user;
    }

    private function patient(User $admin): Patient
    {
        return Patient::query()->create(['facility_id' => currentFacility()->id, 'patient_number' => 'PAT-2026-'.fake()->unique()->numerify('######'), 'first_name' => 'Test', 'last_name' => 'Patient', 'gender' => 'male', 'age_years' => 30, 'patient_status' => 'active', 'created_by' => $admin->id, 'registered_at' => now()]);
    }

    private function visit(User $admin, VisitStatus $status = VisitStatus::AwaitingTriage): Visit
    {
        $department = Department::query()->forCurrentFacility()->firstOrFail();
        $department->update(['clinical_department' => true, 'queue_enabled' => true]);

        return Visit::query()->create(['facility_id' => currentFacility()->id, 'patient_id' => $this->patient($admin)->id, 'visit_number' => 'VIS-2026-'.fake()->unique()->numerify('######'), 'visit_type' => 'new_patient', 'payer_type' => 'insurance', 'destination_department_id' => $department->id, 'current_department_id' => $department->id, 'visit_status' => $status, 'priority' => 'normal', 'registered_at' => now(), 'created_by' => $admin->id]);
    }

    private function opdVisit(User $admin, VisitStatus $status = VisitStatus::InProgress): Visit
    {
        $opd = Department::query()->forCurrentFacility()->where('code', 'OPD')->firstOrFail();

        return $this->visitInDepartment($admin, $opd, $opd, $status);
    }

    private function visitInDepartment(User $admin, Department $currentDepartment, Department $destination, VisitStatus $status): Visit
    {
        $patient = $this->patient($admin);
        $visit = Visit::query()->create([
            'facility_id' => currentFacility()->id,
            'patient_id' => $patient->id,
            'visit_number' => 'VIS-2026-'.fake()->unique()->numerify('######'),
            'visit_type' => 'new_patient',
            'payer_type' => 'cash',
            'destination_department_id' => $destination->id,
            'current_department_id' => $currentDepartment->id,
            'visit_status' => $status,
            'priority' => 'normal',
            'registered_at' => now(),
            'created_by' => $admin->id,
        ]);

        if ($currentDepartment->code === 'OPD') {
            $queue = PatientQueue::query()->create([
                'facility_id' => currentFacility()->id,
                'visit_id' => $visit->id,
                'patient_id' => $patient->id,
                'department_id' => $currentDepartment->id,
                'queue_number' => 'OPD-TST-'.fake()->unique()->numerify('###'),
                'queue_date' => today(),
                'queue_status' => $status === VisitStatus::InConsultation ? QueueStatus::Serving : QueueStatus::Waiting,
                'priority' => 'normal',
                'position' => 1,
                'checked_in_at' => now(),
                'created_by' => $admin->id,
            ]);
            $visit->update(['current_queue_id' => $queue->id]);
        }

        return $visit->refresh();
    }

    private function cashInvoiceForBillingVisit(User $admin, Department $destination, int $amount): Invoice
    {
        $billing = Department::query()->forCurrentFacility()->where('code', 'BIL')->firstOrFail();
        $patient = $this->patient($admin);
        $visit = Visit::query()->create([
            'facility_id' => currentFacility()->id,
            'patient_id' => $patient->id,
            'visit_number' => 'VIS-BIL-'.fake()->unique()->numerify('######'),
            'visit_type' => 'new_patient',
            'payer_type' => 'cash',
            'destination_department_id' => $destination->id,
            'current_department_id' => $billing->id,
            'visit_status' => VisitStatus::Waiting,
            'priority' => 'normal',
            'registered_at' => now(),
            'created_by' => $admin->id,
        ]);

        PatientQueue::query()->create([
            'facility_id' => currentFacility()->id,
            'visit_id' => $visit->id,
            'patient_id' => $patient->id,
            'department_id' => $billing->id,
            'queue_number' => 'BIL-TST-'.fake()->unique()->numerify('###'),
            'queue_date' => today(),
            'queue_status' => QueueStatus::Waiting,
            'priority' => 'normal',
            'position' => 1,
            'checked_in_at' => now(),
            'created_by' => $admin->id,
        ]);

        $invoice = Invoice::query()->create([
            'facility_id' => currentFacility()->id,
            'patient_id' => $patient->id,
            'visit_id' => $visit->id,
            'invoice_number' => 'INV-OPD-AUTH-'.fake()->unique()->numerify('######'),
            'payer_type' => 'cash',
            'invoice_status' => 'pending',
            'subtotal' => $amount,
            'patient_amount' => $amount,
            'total_amount' => $amount,
            'balance_amount' => $amount,
            'status' => 'open',
            'payment_status' => 'unpaid',
            'currency' => 'TZS',
            'issued_at' => now(),
            'created_by' => $admin->id,
        ]);

        InvoiceItem::query()->create([
            'facility_id' => currentFacility()->id,
            'patient_id' => $patient->id,
            'invoice_id' => $invoice->id,
            'item_type' => 'consultation',
            'description' => 'Consultation',
            'description_snapshot' => 'Consultation',
            'quantity' => 1,
            'unit_price' => $amount,
            'gross_amount' => $amount,
            'payer_amount' => $amount,
            'patient_amount' => $amount,
            'insurance_amount' => 0,
            'total_amount' => $amount,
            'net_amount' => $amount,
            'status' => 'pending',
            'created_by' => $admin->id,
        ]);

        return $invoice;
    }

    private function validTriageData(): array
    {
        return [
            'triage_level' => 'urgent',
            'chief_complaint_summary' => 'Fever and shortness of breath',
            'temperature' => '39.5',
            'systolic_bp' => 120,
            'diastolic_bp' => 80,
            'pulse_rate' => 110,
            'respiratory_rate' => 24,
            'oxygen_saturation' => '94',
            'weight_kg' => '70',
            'height_cm' => '170',
            'blood_glucose' => null,
            'muac_cm' => null,
            'pain_score' => 6,
            'consciousness_level' => 'alert',
            'pregnancy_status' => 'not_applicable',
            'gestational_age_weeks' => null,
            'danger_signs' => [],
            'allergies_confirmed' => true,
            'fall_risk' => 'low',
            'infection_risk' => 'suspected',
            'notes' => 'Patient requires prompt clinical review.',
        ];
    }

    private function service(string $name, string $code, string $type, User $admin): Service
    {
        $category = ServiceCategory::query()->first() ?: ServiceCategory::query()->create(['facility_id' => currentFacility()->id, 'name' => 'Clinical', 'code' => 'CLIN', 'category_type' => 'consultation', 'is_active' => true, 'created_by' => $admin->id]);
        $service = Service::query()->create(['facility_id' => currentFacility()->id, 'service_category_id' => $category->id, 'name' => $name, 'code' => $code, 'service_type' => $type, 'requires_payment' => true, 'is_active' => true, 'created_by' => $admin->id]);
        foreach (['cash', 'insurance'] as $payerType) {
            ServicePrice::query()->create(['facility_id' => currentFacility()->id, 'service_id' => $service->id, 'payer_type' => $payerType, 'amount' => 1000, 'currency' => 'TZS', 'is_active' => true, 'created_by' => $admin->id]);
        }

        return $service;
    }

    /** @return array{Visit, LaboratoryResult} */
    private function laboratoryResultFixture(User $admin, User $doctor, string $status, array $values, string $testName): array
    {
        $visit = $this->opdVisit($admin);
        $encounter = app(ClinicalEncounterService::class)->startEncounter($visit, $doctor);
        $service = $this->service($testName, 'LAB-'.fake()->unique()->numerify('######'), 'laboratory_test', $admin);
        $category = LaboratoryTestCategory::factory()->create(['facility_id' => currentFacility()->id, 'created_by' => $admin->id]);
        $specimen = SpecimenType::factory()->create(['facility_id' => currentFacility()->id, 'created_by' => $admin->id]);
        $test = LaboratoryTest::factory()->create([
            'facility_id' => currentFacility()->id,
            'service_id' => $service->id,
            'laboratory_test_category_id' => $category->id,
            'specimen_type_id' => $specimen->id,
            'name' => $testName,
            'code' => 'TST'.fake()->unique()->numerify('######'),
            'created_by' => $admin->id,
        ]);
        $order = app(ClinicalEncounterService::class)->addLabOrder(
            $encounter->refresh(),
            ['service_ids' => [$service->id]],
            $doctor,
        );
        $order->update(['status' => 'result_ready']);
        $item = $order->items()->firstOrFail();
        $item->update(['status' => 'sample_accepted', 'result_status' => $status, 'result_entered_at' => now()->subMinutes(10)]);
        $result = LaboratoryResult::query()->create([
            'facility_id' => currentFacility()->id,
            'laboratory_order_id' => $order->id,
            'laboratory_order_item_id' => $item->id,
            'laboratory_test_id' => $test->id,
            'result_version' => 1,
            'result_status' => $status,
            'comments' => 'Reviewed by laboratory',
            'entered_by' => $admin->id,
            'entered_at' => now()->subMinutes(10),
            'verified_by' => in_array($status, ['verified', 'released'], true) ? $admin->id : null,
            'verified_at' => in_array($status, ['verified', 'released'], true) ? now()->subMinutes(5) : null,
            'released_by' => $status === 'released' ? $admin->id : null,
            'released_at' => $status === 'released' ? now() : null,
            'created_by' => $admin->id,
        ]);

        foreach ($values as $index => $value) {
            LaboratoryResultValue::query()->create([
                'laboratory_result_id' => $result->id,
                'parameter_name_snapshot' => $value['parameter'],
                'parameter_code_snapshot' => 'P'.($index + 1),
                'result_type' => $value['type'],
                'numeric_value' => $value['numeric_value'] ?? null,
                'text_value' => $value['text_value'] ?? null,
                'selected_value' => $value['selected_value'] ?? null,
                'unit_snapshot' => $value['unit'] ?? null,
                'reference_range_snapshot' => $value['range'] ?? null,
                'abnormal_flag' => $value['flag'],
                'is_critical' => $value['critical'] ?? false,
                'sort_order' => $index,
                'created_by' => $admin->id,
            ]);
        }

        return [$visit->refresh(), $result->refresh()];
    }

    private function medicine(User $admin): Medicine
    {
        $unit = MedicineUnit::query()->firstOrCreate(
            ['facility_id' => currentFacility()->id, 'name' => 'Tablet'],
            ['symbol' => 'tab', 'is_active' => true, 'created_by' => $admin->id],
        );

        $category = ServiceCategory::query()->first() ?: ServiceCategory::query()->create(['facility_id' => currentFacility()->id, 'name' => 'Clinical', 'code' => 'CLIN', 'category_type' => 'consultation', 'is_active' => true, 'created_by' => $admin->id]);
        $service = Service::query()->create([
            'facility_id' => currentFacility()->id,
            'service_category_id' => $category->id,
            'name' => 'Explicitly free test medicine '.fake()->unique()->numerify('######'),
            'code' => 'FREE-MED-'.fake()->unique()->numerify('######'),
            'service_type' => 'medicine',
            'requires_payment' => false,
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        return Medicine::query()->create([
            'facility_id' => currentFacility()->id,
            'service_id' => $service->id,
            'purchase_unit_id' => $unit->id,
            'dispensing_unit_id' => $unit->id,
            'name' => 'Paracetamol',
            'code' => 'PCM-'.fake()->unique()->numerify('######'),
            'strength' => '500mg',
            'pack_size' => 1,
            'purchase_to_dispensing_factor' => 1,
            'is_active' => true,
            'created_by' => $admin->id,
        ]);
    }
}
