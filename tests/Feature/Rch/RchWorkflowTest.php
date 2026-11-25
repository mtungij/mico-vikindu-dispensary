<?php

namespace Tests\Feature\Rch;

use App\Enums\RchService;
use App\Livewire\Patients\Index as Reception;
use App\Livewire\Pharmacy\Queue as PharmacyQueue;
use App\Livewire\Rch\Children\GrowthMeasurement;
use App\Livewire\Rch\Encounter;
use App\Livewire\Rch\FamilyPlanning\Visit as FamilyPlanningScreen;
use App\Livewire\Rch\Immunization\Administer;
use App\Livewire\Rch\Postnatal\Visit as PncScreen;
use App\Livewire\Rch\Pregnancies\AncVisit as AncScreen;
use App\Livewire\Rch\Queue;
use App\Models\ActivityLog;
use App\Models\AncVisit;
use App\Models\ChildGrowthMeasurement;
use App\Models\Department;
use App\Models\Facility;
use App\Models\FamilyPlanningVisit;
use App\Models\ImmunizationAdministration;
use App\Models\InsuranceProvider;
use App\Models\Invoice;
use App\Models\LaboratoryTest;
use App\Models\LaboratoryTestCategory;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\MedicineUnit;
use App\Models\Patient;
use App\Models\PaymentMethod;
use App\Models\PncVisit;
use App\Models\Pregnancy;
use App\Models\RchEncounter;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\SpecimenType;
use App\Models\StaffProfile;
use App\Models\StockLocation;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Vaccine;
use App\Models\Visit;
use App\Services\AppointmentService;
use App\Services\LaboratoryOrderItemDecisionService;
use App\Services\LaboratoryResultReleaseService;
use App\Services\LaboratoryResultService;
use App\Services\LaboratoryResultVerificationService;
use App\Services\LaboratorySampleService;
use App\Services\PaymentConfirmationService;
use App\Services\PharmacyDispensingService;
use App\Services\PregnancyService;
use App\Services\ReceptionWorkflowService;
use App\Services\StockReceivingService;
use App\Services\TriageService;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\MedicineUnitSeeder;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RchWorkflowServiceSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\ServiceCategorySeeder;
use Database\Seeders\ServicePriceSeeder;
use Database\Seeders\ServiceSeeder;
use Database\Seeders\StockLocationSeeder;
use Database\Seeders\VaccineSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RchWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Facility $facility;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->superAdmin()->create();
        $this->facility = Facility::factory()->create(['setup_completed_at' => now(), 'created_by' => $this->admin->id]);
        $this->actingAs($this->admin);
        $this->seed([
            PermissionSeeder::class, RoleSeeder::class, RolePermissionSeeder::class, DepartmentSeeder::class,
            ServiceCategorySeeder::class, ServiceSeeder::class, ServicePriceSeeder::class, RchWorkflowServiceSeeder::class,
            PaymentMethodSeeder::class, StockLocationSeeder::class, MedicineUnitSeeder::class,
        ]);
    }

    public static function programmes(): array
    {
        return [
            'Child Growth Monitoring' => [RchService::ChildMonitoring, '500.00'],
            'ANC' => [RchService::Anc, '1000.00'],
            'PNC' => [RchService::Pnc, '1000.00'],
            'Family Planning' => [RchService::FamilyPlanning, '0.00'],
            'Ultrasound' => [RchService::Ultrasound, '15000.00'],
        ];
    }

    #[DataProvider('programmes')]
    public function test_new_rch_registration_bills_only_the_selected_service_at_its_price(RchService $programme, string $price): void
    {
        $receptionist = $this->staff('receptionist');
        Livewire::actingAs($receptionist)->test(Reception::class)
            ->call('create')
            ->set('personal.first_name', 'Rch')
            ->set('personal.last_name', 'Registration')
            ->set('personal.gender', 'female')
            ->set('personal.age_years', $programme === RchService::ChildMonitoring ? 3 : 26)
            ->set('payer.payer_type', 'cash')
            ->set('visit.destination_department_id', $this->department('RCH')->id)
            ->set('step', 5)->assertSee('RCH service')
            ->assertSee('ANC Consultation')->assertSee('PNC Consultation')->assertSee('Child Growth Monitoring')
            ->assertSee('Family Planning')->assertSee('Ultrasound')
            ->set('visit.consultation_service_id', $this->service($programme)->id)
            ->set('visit.require_payment_before_service', false)
            ->set('step', 6)->assertSee('TSh '.number_format((float) $price, 2))
            ->call('save')->assertHasNoErrors();

        $visit = Visit::query()->sole();
        $this->assertSame('RCH', $visit->destinationDepartment->code);
        $this->assertSame($price === '0.00' ? 'TRI' : 'BIL', $visit->currentDepartment->code);
        $this->assertSame($price === '0.00' ? 'awaiting_triage' : 'awaiting_payment', $visit->visit_status->value);
        $charge = $visit->invoice->items()->sole();
        $this->assertSame($this->service($programme)->id, $charge->service_id);
        $this->assertSame($price, $charge->unit_price);
        $this->assertSame($price, $visit->invoice->balance_amount);
        $this->assertSame(0, $visit->invoice->payments()->count());
    }

    public function test_registration_retries_and_catalogue_reseeding_preserve_existing_records_and_prices(): void
    {
        $service = $this->service(RchService::ChildMonitoring);
        $service->prices()->sole()->update(['amount' => 725]);
        $this->seed(RchWorkflowServiceSeeder::class);
        $this->assertSame('725.00', $service->prices()->sole()->amount);
        $key = (string) Str::uuid();
        $first = $this->register(RchService::ChildMonitoring, $key);
        $retry = $this->register(RchService::ChildMonitoring, $key);
        $this->assertSame($first->id, $retry->id);
        $this->assertDatabaseCount('visits', 1);
        $this->assertDatabaseCount('patients', 1);
        $this->assertDatabaseCount('invoices', 1);
        $this->assertDatabaseCount('invoice_items', 1);

        $this->expectException(ValidationException::class);
        app(ReceptionWorkflowService::class)->openReturningPatientVisit(
            $first->patient, [], $this->visitData(RchService::Anc), [], $this->admin, 'Detailed override reason',
        );
    }

    public function test_reception_opens_a_returning_rch_visit_on_the_existing_patient(): void
    {
        $patient = Patient::factory()->create(['facility_id' => $this->facility->id]);
        $receptionist = $this->staff('receptionist');

        Livewire::actingAs($receptionist)->test(Reception::class)
            ->call('create')
            ->call('selectExistingPatient', $patient->id)
            ->set('visit.destination_department_id', $this->department('RCH')->id)
            ->set('visit.consultation_service_id', $this->service(RchService::Pnc)->id)
            ->set('step', 6)->assertSee('TSh 0.00')
            ->call('save')->assertHasNoErrors();

        $visit = Visit::query()->sole();
        $this->assertSame($patient->id, $visit->patient_id);
        $this->assertSame('returning_patient', $visit->visit_type->value);
        $this->assertSame('1000.00', $visit->invoice->items()->sole()->total_amount);
        $this->assertDatabaseCount('patients', 1);
    }

    public function test_cashier_triage_rch_completion_and_follow_up_reuse_the_patient(): void
    {
        $visit = $this->register(RchService::ChildMonitoring);
        $doctor = $this->staff('doctor');
        $this->actingAs($doctor)->get(route('rch.encounter', $visit))->assertStatus(409);
        Livewire::actingAs($doctor)->test(Queue::class)->assertDontSee($visit->patient->fullName());
        $this->pay($visit->invoice, 500);
        $visit->refresh();
        $this->assertSame('TRI', $visit->currentDepartment->code);
        Livewire::actingAs($doctor)->test(Queue::class)->assertDontSee($visit->patient->fullName());
        $this->triage($visit);
        Livewire::actingAs($doctor)->test(Queue::class)->assertSee($visit->patient->fullName());
        $this->actingAs($doctor)->get(route('rch.encounter', $visit))->assertOk()->assertSee('Child Growth Monitoring')->assertSee('36.50');
        Livewire::actingAs($doctor)->test(Encounter::class, ['visit' => $visit])
            ->set('form.clinical_summary', 'Child monitoring notes')
            ->call('save')->assertHasNoErrors()
            ->set('followUpDate', today()->addWeek()->toDateString())
            ->set('followUpTime', '10:00')
            ->set('followUpReason', 'Routine follow-up')
            ->call('bookFollowUp')->assertHasNoErrors()
            ->call('complete')->assertRedirect(route('rch.index'));
        $this->assertDatabaseCount('rch_encounters', 1);
        $this->assertSame('Child monitoring notes', RchEncounter::query()->sole()->clinical_summary);
        $this->assertSame('completed', $visit->refresh()->visit_status->value);
        $this->assertSame('completed', $visit->clinicalEncounters()->sole()->status->value);
        $this->assertFalse($visit->queues()->where('department_id', $this->department('OPD')->id)->exists());

        $appointment = RchEncounter::query()->sole()->clinicalEncounter->appointments()->sole();
        $receptionist = $this->staff('receptionist');
        $this->actingAs($receptionist);
        $return = app(AppointmentService::class)->checkIn($appointment, $receptionist)->visit;
        $this->assertSame($visit->patient_id, $return->patient_id);
        $this->assertNotSame($visit->id, $return->id);
        $this->assertSame('returning_patient', $return->visit_type->value);
        $this->assertSame('500.00', $return->invoice->items()->sole()->total_amount);
        $this->assertSame($return->id, app(AppointmentService::class)->checkIn($appointment, $receptionist)->visit_id);
        $this->assertDatabaseCount('patients', 1);
    }

    public function test_insured_rch_visit_with_configured_coverage_goes_straight_to_triage(): void
    {
        $insurance = InsuranceProvider::query()->create([
            'facility_id' => $this->facility->id, 'name' => 'RCH test insurer', 'code' => 'RCH-INS', 'provider_type' => 'private_insurance', 'is_active' => true,
        ]);
        $this->service(RchService::Anc)->prices()->create([
            'facility_id' => $this->facility->id, 'payer_type' => 'insurance', 'insurance_provider_id' => $insurance->id,
            'amount' => 1000, 'currency' => 'TZS', 'is_active' => true,
        ]);
        $visit = $this->register(RchService::Anc, null, [
            'payer_type' => 'insurance', 'insurance_provider_id' => $insurance->id, 'membership_number' => 'INS-RCH-001',
        ]);
        $this->assertSame('0.00', $visit->invoice->balance_amount);
        $this->assertSame('1000.00', $visit->invoice->insurance_amount);
        $this->assertSame('TRI', $visit->currentDepartment->code);
        $this->triage($visit);
        $visit->payerProfile->update(['coverage_status' => 'active']);
        $procedure = $this->pricedService('Covered procedure', 'PRINS', 'RCH', 'procedure', 5000);
        $procedure->prices()->create([
            'facility_id' => $this->facility->id, 'payer_type' => 'insurance', 'insurance_provider_id' => $insurance->id,
            'amount' => 3500, 'currency' => 'TZS', 'is_active' => true,
        ]);
        $test = $this->laboratoryService('Covered laboratory test', 8700);
        $test->prices()->create([
            'facility_id' => $this->facility->id, 'payer_type' => 'insurance', 'insurance_provider_id' => $insurance->id,
            'amount' => 5600, 'currency' => 'TZS', 'is_active' => true,
        ]);
        Livewire::actingAs($this->staff('doctor'))->test(Encounter::class, ['visit' => $visit])
            ->assertSee('ANC Consultation')->assertSee('TZS 3,500.00')->assertSee('TZS 5,600.00')->assertDontSee('TZS 8,700.00')
            ->set('procedureIds', [$procedure->id])->call('orderProcedures')->assertHasNoErrors()
            ->set('testIds', [$test->id])->call('orderTests')->assertHasNoErrors();
        $order = $visit->clinicalEncounters()->sole()->procedureOrders()->sole();
        $this->assertSame('ordered', $order->status->value);
        $this->assertSame('3500.00', $order->invoiceItem->insurance_amount);
        $this->assertSame('0.00', $order->invoiceItem->patient_amount);
        $testCharge = $visit->laboratoryOrders()->sole()->items()->sole()->invoiceItem;
        $this->assertSame('5600.00', $testCharge->insurance_amount);
        $this->assertSame('0.00', $testCharge->patient_amount);
    }

    public function test_laboratory_selective_payment_and_released_result_review_are_independent_of_unperformed_tests(): void
    {
        $visit = $this->triagedVisit(RchService::Pnc);
        $doctor = $this->staff('doctor');
        $tech = $this->staff('laboratory-technician');
        $doctor->givePermissionTo('laboratory-orders.cancel');
        $hb = $this->laboratoryService('Hemoglobin', 4500);
        $rpr = $this->laboratoryService('RPR', 4000);
        $free = $this->laboratoryService('Urinalysis', 0);
        $screen = Livewire::actingAs($doctor)->test(Encounter::class, ['visit' => $visit])
            ->set('testIds', [$hb->id, $rpr->id, $free->id])
            ->call('orderTests')->assertHasNoErrors()
            ->assertSet('testIds', [])
            ->set('testIds', [$hb->id])->call('orderTests')->assertHasErrors('service_ids')
            ->assertSet('testIds', [$hb->id]);

        $order = $visit->laboratoryOrders()->sole();
        $this->assertSame('rch', $order->source);
        $paid = $order->items()->where('service_id', $hb->id)->sole();
        $unpaid = $order->items()->where('service_id', $rpr->id)->sole();
        $freeItem = $order->items()->where('service_id', $free->id)->sole();
        $this->pay($visit->invoice, 4500, [$paid->invoice_item_id => 4500]);
        $this->assertTrue($paid->isFinanciallyCleared());
        $this->assertTrue($freeItem->isFinanciallyCleared());
        $this->assertFalse($unpaid->isFinanciallyCleared());
        $this->actingAs($doctor);
        $screen->call('complete')->assertRedirect(route('rch.index'));
        $this->assertNotSame('completed', $visit->refresh()->visit_status->value);

        $this->actingAs($tech);
        app(LaboratorySampleService::class)->collectSample($order, ['order_item_ids' => [$paid->id, $freeItem->id]], $tech, true);
        $result = app(LaboratoryResultService::class)->saveForItem($paid, ['main' => ['value' => '12.75']], $tech, true);
        $this->actingAs($this->admin);
        $result = app(LaboratoryResultVerificationService::class)->verify($result, $this->admin);

        $this->actingAs($doctor);
        $screen->call('$refresh')->assertDontSee('12.75');
        $this->actingAs($this->admin);
        app(LaboratoryResultReleaseService::class)->release($result, $this->admin);
        $this->actingAs($doctor);
        $screen->call('$refresh')->assertSee('12.75');

        $screen->set('testCancellationReasons.'.$unpaid->id, 'Test no longer required.')
            ->call('cancelTest', $unpaid->id)->assertHasNoErrors();
        $this->assertSame('cancelled', $unpaid->refresh()->status);
        $this->actingAs($tech);
        app(LaboratoryOrderItemDecisionService::class)->decide($freeItem, 'not_performed', 'no_longer_required', 'Repeat testing not needed.', $tech);
        $this->assertSame('0.00', $visit->invoice->refresh()->balance_amount);
        $this->assertSame('awaiting_doctor_review', $visit->refresh()->visit_status->value);
        $this->assertSame('RCH', $visit->currentDepartment->code);
        $this->actingAs($doctor);
        $screen->call('reviewResult', $result->id)->assertHasNoErrors();
        $this->assertSame($doctor->id, $result->refresh()->reviewed_by_clinician);
        $this->assertSame('completed', $visit->refresh()->visit_status->value);
        $this->assertFalse($visit->queues()->where('department_id', $this->department('OPD')->id)->exists());
        $this->assertSame(4, $visit->invoice->items()->count());
    }

    public function test_family_planning_remains_free_with_a_stale_cash_tariff_and_bills_only_ordered_care(): void
    {
        $familyPlanning = $this->service(RchService::FamilyPlanning);
        $familyPlanning->update(['requires_payment' => true]);
        $familyPlanning->prices()->sole()->update(['amount' => 5000]);
        $visit = $this->triagedVisit(RchService::FamilyPlanning);
        $consultation = $visit->invoice->items()->sole();
        $this->assertSame('0.00', $consultation->unit_price);
        $this->assertSame('covered', $consultation->status);
        $this->assertSame(0, $visit->invoice->payments()->count());
        $this->assertFalse($visit->queues()->where('department_id', $this->department('BIL')->id)->exists());

        $procedure = $this->pricedService('Selected procedure', 'FPTEST', 'RCH', 'procedure', 3500);
        $unselected = $this->pricedService('Unselected procedure', 'FPOTHER', 'RCH', 'procedure', 4000);
        $medicine = $this->medicine('Prescribed medicine', 'FPMED', StockLocation::query()->where('code', 'MAIN')->sole());
        $doctor = $this->staff('doctor');
        $screen = Livewire::actingAs($doctor)->test(Encounter::class, ['visit' => $visit])
            ->set('procedureIds', [$procedure->id])->call('orderProcedures')->assertHasNoErrors()
            ->set('procedureIds', [$procedure->id])->call('orderProcedures')->assertHasNoErrors()
            ->set('medicine.medicine_id', $medicine->id)
            ->set('medicine.dose', '1 tablet')->set('medicine.route', 'oral')->set('medicine.frequency', 'Once daily')
            ->set('medicine.duration_value', 3)
            ->set('medicine.quantity', 3)->set('medicine.instructions', 'Follow the recorded directions.')
            ->call('addMedicine')->assertHasNoErrors();
        $clinical = $visit->clinicalEncounters()->sole();
        $prescription = $clinical->prescriptions()->sole();
        $this->assertSame(2, $visit->invoice->items()->count());
        $screen->call('sendPrescription', $prescription->id)->assertHasNoErrors()
            ->call('sendPrescription', $prescription->id)->assertHasNoErrors();

        $this->assertSame('3800.00', $visit->invoice->refresh()->balance_amount);
        $this->assertSame('3500.00', $clinical->procedureOrders()->sole()->invoiceItem->total_amount);
        $this->assertSame('300.00', $prescription->items()->sole()->invoiceItem->total_amount);
        $this->assertSame(3, $visit->invoice->items()->count());
        $this->assertFalse($visit->invoice->items()->where('service_id', $unselected->id)->exists());
        $screen->call('complete')->assertRedirect(route('rch.index'));
        $this->assertSame('completed', $clinical->refresh()->status->value);
        $this->assertSame('awaiting_payment', $prescription->refresh()->status->value);
        $this->assertSame('awaiting_payment', $clinical->procedureOrders()->sole()->status->value);
    }

    public function test_child_growth_requires_birth_details_before_registration_can_bill(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Child Growth Monitoring requires a valid date of birth');

        try {
            app(ReceptionWorkflowService::class)->registerNewPatientAndVisit(
                ['first_name' => 'New', 'last_name' => 'Child', 'gender' => 'female', 'patient_status' => 'active'],
                ['payer_type' => 'cash', 'is_primary' => true], $this->visitData(RchService::ChildMonitoring), [], $this->admin,
            );
        } finally {
            $this->assertDatabaseCount('visits', 0);
            $this->assertDatabaseCount('invoices', 0);
        }
    }

    public function test_prescription_selective_payment_partial_dispensing_and_injection_recording(): void
    {
        $visit = $this->triagedVisit(RchService::Anc);
        $doctor = $this->staff('doctor');
        $doctor->givePermissionTo('rch.administer-medication');
        $pharmacist = $this->staff('pharmacist');
        $location = StockLocation::query()->where('code', 'MAIN')->sole();
        $oral = $this->medicine('Folic Acid', 'FOL', $location);
        $injection = $this->medicine('Tetanus injection', 'TT', $location);
        $screen = Livewire::actingAs($doctor)->test(Encounter::class, ['visit' => $visit]);
        foreach ([[$oral, '1 tablet', 'oral', '3'], [$injection, '1 mL', 'intramuscular', '1']] as [$medicine, $dose, $route, $quantity]) {
            $screen->set('medicine.medicine_id', $medicine->id)
                ->set('medicine.dose', $dose)->set('medicine.route', $route)
                ->set('medicine.frequency', 'Once daily')->set('medicine.duration_value', '1')
                ->set('medicine.quantity', $quantity)->set('medicine.instructions', 'Test directions recorded by the clinician.')
                ->call('addMedicine')->assertHasNoErrors();
        }
        $prescription = RchEncounter::query()->sole()->clinicalEncounter->prescriptions()->sole();
        $this->assertSame('draft', $prescription->status->value);
        $this->assertSame(1, $visit->invoice->items()->count());
        $screen->call('sendPrescription', $prescription->id)->assertHasNoErrors()
            ->call('sendPrescription', $prescription->id)->assertHasNoErrors();
        $this->assertSame(3, $visit->invoice->items()->count());

        $oralItem = $prescription->items()->where('medicine_id', $oral->id)->sole();
        $injectionItem = $prescription->items()->where('medicine_id', $injection->id)->sole();
        $dispensing = app(PharmacyDispensingService::class);
        $oralLine = ['prescription_item_id' => $oralItem->id, 'medicine_id' => $oral->id, 'quantity' => 1];
        $injectionLine = ['prescription_item_id' => $injectionItem->id, 'medicine_id' => $injection->id, 'quantity' => 1];
        $this->actingAs($pharmacist);
        try {
            $dispensing->dispense($prescription, [$oralLine], $location, $pharmacist);
            $this->fail('An unpaid item was dispensed.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('payment', $exception->errors());
        }
        $this->pay($visit->invoice, 100, [$injectionItem->invoice_item_id => 100]);
        Livewire::actingAs($pharmacist)->test(PharmacyQueue::class)->assertSee($prescription->prescription_number);
        try {
            $dispensing->dispense($prescription, [$oralLine], $location, $pharmacist);
            $this->fail('Payment of another medicine unlocked the unpaid item.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('quantity', $exception->errors());
        }
        $dispensedInjection = $dispensing->dispense($prescription, [$injectionLine], $location, $pharmacist);
        $this->assertSame('partially_dispensed', $prescription->refresh()->status->value);
        $this->assertDatabaseCount('medication_administrations', 0);

        $this->actingAs($doctor);
        $time = now()->subMinute()->format('Y-m-d\TH:i');
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $screen->set('injectionItem', $injectionItem->id)->set('injectionTime', $time)
                ->set('injectionDose', '1 mL')->set('injectionQuantity', '1')->call('recordInjection')->assertHasNoErrors();
        }
        $this->assertDatabaseCount('medication_administrations', 1);
        $this->assertDatabaseHas('medication_administrations', ['prescription_item_id' => $injectionItem->id, 'administered_by' => $doctor->id]);
        $screen->call('complete')->assertRedirect(route('rch.index'));
        $this->assertNotSame('completed', $visit->refresh()->visit_status->value);

        $this->actingAs($this->admin);
        try {
            $dispensing->reverseDispensing($dispensedInjection, $this->admin, 'Reverse test');
            $this->fail('Already administered stock was removed from dispensing history.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('dispensing', $exception->errors());
        }
        $this->pay($visit->invoice, 300, [$oralItem->invoice_item_id => 300]);
        $this->actingAs($pharmacist);
        $dispensing->dispense($prescription, [[...$oralLine, 'quantity' => 3]], $location, $pharmacist);
        $this->assertSame('dispensed', $prescription->refresh()->status->value);
        $this->assertSame('completed', $visit->refresh()->visit_status->value);
        $this->assertSame('6.000', MedicineBatch::query()->where('batch_number', 'FOL-early')->sole()->available_quantity);
        $this->assertSame('10.000', MedicineBatch::query()->where('batch_number', 'FOL-late')->sole()->available_quantity);
        $this->actingAs($doctor);
        $screen->call('complete')->assertRedirect(route('rch.index'));
    }

    public function test_reception_ultrasound_uses_the_paid_item_and_existing_procedure_order(): void
    {
        $visit = $this->triagedVisit(RchService::Ultrasound);
        $doctor = $this->staff('doctor');
        $doctor->givePermissionTo('rch.complete-ultrasound');
        $screen = Livewire::actingAs($doctor)->test(Encounter::class, ['visit' => $visit])
            ->set('form.clinical_summary', 'Ultrasound consultation')
            ->call('complete')->assertHasErrors('procedureIds')
            ->set('procedureIds', [$this->service(RchService::Ultrasound)->id])->call('orderProcedures')->assertHasNoErrors()
            ->set('procedureIds', [$this->service(RchService::Ultrasound)->id])->call('orderProcedures')->assertHasNoErrors();
        $order = RchEncounter::query()->sole()->clinicalEncounter->procedureOrders()->sole();
        $this->assertSame($this->service(RchService::Ultrasound)->id, $order->service_id);
        $this->assertSame('RCH Ultrasound', $order->procedure_name_snapshot);
        $this->assertSame($order->invoice_item_id, $visit->invoice->items()->sole()->id);
        $this->assertSame('ordered', $order->status->value);
        $screen->call('complete')->assertRedirect(route('rch.index'));
        $this->assertNotSame('completed', $visit->refresh()->visit_status->value);
        $screen
            ->set('procedureFindings.'.$order->id, 'Ultrasound report recorded')
            ->call('completeProcedure', $order->id)->assertHasNoErrors()
            ->assertSee('Ultrasound report recorded')
            ->call('complete')->assertRedirect(route('rch.index'));
    }

    public function test_ultrasound_ordered_in_rch_requires_its_own_payment_and_does_not_repeat_triage(): void
    {
        $ultrasound = $this->service(RchService::Ultrasound);
        $ultrasound->prices()->sole()->update(['amount' => 18250]);
        $this->seed(RchWorkflowServiceSeeder::class);

        $visit = $this->triagedVisit(RchService::Anc);
        $doctor = $this->staff('doctor');
        $doctor->givePermissionTo('rch.complete-ultrasound');
        $screen = Livewire::actingAs($doctor)->test(Encounter::class, ['visit' => $visit])
            ->set('procedureIds', [$this->service(RchService::Ultrasound)->id])->call('orderProcedures')->assertHasNoErrors();
        $order = $visit->clinicalEncounters()->sole()->procedureOrders()->sole();
        $this->assertSame($ultrasound->id, $order->service_id);
        $this->assertSame('18250.00', $order->invoiceItem->patient_amount);
        $this->assertSame('awaiting_payment', $order->status->value);

        $screen->set('procedureFindings.'.$order->id, 'Clinical scan findings')
            ->call('completeProcedure', $order->id)->assertHasErrors('procedure');
        $this->pay($visit->invoice, 18250, [$order->invoice_item_id => 18250]);
        $this->assertSame('ordered', $order->refresh()->status->value);
        $this->assertSame('RCH', $visit->refresh()->currentDepartment->code);
        $this->assertSame(1, $visit->queues()->where('department_id', $this->department('TRI')->id)->count());

        $this->actingAs($doctor);
        $screen->call('completeProcedure', $order->id)->assertHasNoErrors()
            ->call('complete')->assertRedirect(route('rch.index'));
    }

    public function test_cross_facility_and_unauthorized_encounter_requests_are_rejected(): void
    {
        $visit = $this->triagedVisit(RchService::ChildMonitoring);
        $receptionist = $this->staff('receptionist');
        $this->actingAs($receptionist)->get(route('rch.encounter', $visit))->assertForbidden();

        $other = Facility::factory()->create(['setup_completed_at' => now()]);
        $this->actingAs($this->staff('doctor', $other))->get(route('rch.encounter', $visit))->assertNotFound();
        $this->assertDatabaseCount('rch_encounters', 0);

        $nurse = $this->staff('rch-nurse');
        Livewire::actingAs($nurse)->test(Encounter::class, ['visit' => $visit])
            ->set('form.clinical_summary', 'Authorized clinical note')
            ->call('save')->assertHasNoErrors()
            ->call('addMedicine')->assertForbidden();
    }

    public function test_multiple_procedures_bill_separately_and_can_be_performed_after_consultation_sign_off(): void
    {
        $visit = $this->triagedVisit(RchService::Anc);
        $first = $this->pricedService('Procedure A', 'PRA', 'RCH', 'procedure', 3500);
        $second = $this->pricedService('Procedure B', 'PRB', 'RCH', 'procedure', 7000);
        $foreign = Service::factory()->create(['service_type' => 'procedure']);
        $doctor = $this->staff('doctor');
        $doctor->givePermissionTo('rch.complete-procedure');
        $screen = Livewire::actingAs($doctor)->test(Encounter::class, ['visit' => $visit])
            ->assertSee('3,500.00')->assertSee('7,000.00')
            ->call('complete')->assertHasErrors('clinical_content')
            ->set('procedureIds', [$first->id, $foreign->id])->call('orderProcedures')->assertHasErrors('procedureIds')
            ->assertSet('procedureIds', [$first->id, $foreign->id])
            ->set('procedureIds', [$first->id, $second->id])->call('orderProcedures')->assertHasNoErrors()
            ->assertSet('procedureIds', [])
            ->set('procedureIds', [$first->id, $second->id])->call('orderProcedures')->assertHasNoErrors();

        $clinical = $visit->clinicalEncounters()->sole();
        $this->assertSame(2, $clinical->procedureOrders()->count());
        $this->assertSame(3, $visit->invoice->items()->count());
        $orders = $clinical->procedureOrders()->with('invoiceItem')->get()->keyBy('service_id');
        $firstOrder = $orders[$first->id];
        $secondOrder = $orders[$second->id];
        $this->assertSame('3500.00', $firstOrder->invoiceItem->patient_amount);
        $this->assertSame('7000.00', $secondOrder->invoiceItem->patient_amount);
        $screen->call('complete')->assertRedirect(route('rch.index'));
        $screen->call('complete')->assertRedirect(route('rch.index'));
        $this->assertSame($doctor->id, $clinical->refresh()->completed_by);
        $this->assertNotNull($clinical->completed_at);
        $this->assertSame(1, ActivityLog::query()->where('event', 'rch_encounter_completed')->count());
        $this->assertNotSame('completed', $visit->refresh()->visit_status->value);
        $this->assertSame('awaiting_payment', $firstOrder->refresh()->status->value);

        $screen->set('procedureFindings.'.$firstOrder->id, 'Recorded findings')
            ->call('completeProcedure', $firstOrder->id)->assertHasErrors('procedure');
        $this->pay($visit->invoice, 3500, [$firstOrder->invoice_item_id => 3500]);
        $this->actingAs($doctor);
        $screen->call('completeProcedure', $firstOrder->id)->assertHasNoErrors();
        $this->assertSame('completed', $firstOrder->refresh()->status->value);
        $this->assertSame('awaiting_payment', $secondOrder->refresh()->status->value);
        $this->assertNotSame('completed', $visit->refresh()->visit_status->value);

        $this->pay($visit->invoice, 7000, [$secondOrder->invoice_item_id => 7000]);
        $this->actingAs($doctor);
        $screen->set('procedureFindings.'.$secondOrder->id, 'Second set of findings')
            ->call('completeProcedure', $secondOrder->id)->assertHasNoErrors();
        $this->assertSame('completed', $visit->refresh()->visit_status->value);
        $screen->call('$refresh')->assertDontSee('Select procedures')->assertDontSee('Select laboratory tests');
        $screen->set('form.clinical_summary', 'Attempt to edit signed consultation')->call('save')->assertForbidden();
    }

    public static function linkedProgrammes(): array
    {
        return [
            'ANC' => [RchService::Anc, AncScreen::class, AncVisit::class, 'assessment'],
            'Family Planning' => [RchService::FamilyPlanning, FamilyPlanningScreen::class, FamilyPlanningVisit::class, 'notes'],
            'Growth' => [RchService::ChildMonitoring, GrowthMeasurement::class, ChildGrowthMeasurement::class, 'notes'],
        ];
    }

    #[DataProvider('linkedProgrammes')]
    public function test_programme_worklist_opens_and_saves_one_record_against_the_existing_visit(RchService $programme, string $component, string $model, string $notesField): void
    {
        $visit = $this->register($programme);
        $nurse = $this->staff('rch-nurse');
        $route = route($programme->visitRoute(), $visit);
        $this->actingAs($nurse)->get(route('rch.index', ['programme' => $programme->value]))->assertDontSee($route, false);
        $this->get($route)->assertStatus(409);
        if ((float) $visit->invoice->balance_amount > 0) {
            $this->pay($visit->invoice, (int) $visit->invoice->balance_amount);
        }
        $this->triage($visit);
        $this->actingAs($nurse)->get(route('rch.index', ['programme' => $programme->value]))->assertSee($route, false);
        $this->get($route)->assertOk()->assertSee($visit->patient->fullName());
        $screen = Livewire::actingAs($nurse)->test($component, ['linkedVisit' => $visit->id])
            ->set('form.'.$notesField, 'Notes from the linked visit')
            ->call('save')->assertHasNoErrors();
        $screen->call('save')->assertHasNoErrors();
        $this->get($route)->assertOk();

        $record = $model::query()->sole();
        $encounter = RchEncounter::query()->sole();
        $this->assertSame($visit->id, $record->visit_id);
        $this->assertSame($encounter->id, $record->rch_encounter_id);
        $this->assertSame($visit->patient_id, $record->patient_id ?? $record->child_patient_id);
        $this->assertDatabaseCount('patients', 1);
        $this->assertSame(1, $visit->invoice->items()->count());
    }

    public function test_linked_anc_reuses_existing_pregnancy_and_preserves_unlinked_history(): void
    {
        $visit = $this->triagedVisit(RchService::Anc);
        $this->actingAs($this->admin);
        $pregnancy = app(PregnancyService::class)->register($visit->patient, [], $this->admin);
        $original = $pregnancy->ancVisits()->create([
            'facility_id' => $visit->facility_id, 'patient_id' => $visit->patient_id,
            'anc_visit_number' => 1, 'visit_date' => today()->subWeek()->toDateString(),
            'gestational_age_weeks' => 12, 'assessment' => 'Existing ANC history',
        ]);

        $nurse = $this->staff('rch-nurse');
        $this->actingAs($nurse)->get(route('rch.visits.pnc', $visit))->assertNotFound();
        Livewire::actingAs($nurse)->test(AncScreen::class, ['linkedVisit' => $visit->id])
            ->set('form.assessment', 'New ANC visit')->call('save')->assertHasNoErrors();
        $this->assertSame(1, Pregnancy::query()->count());
        $this->assertSame('Existing ANC history', $original->refresh()->assessment);
        $this->assertNull($original->rch_encounter_id);
        $linked = $pregnancy->ancVisits()->where('visit_id', $visit->id)->sole();
        $this->assertSame(2, $linked->anc_visit_number);
        $this->assertSame(RchEncounter::query()->sole()->id, $linked->rch_encounter_id);
    }

    public function test_linked_immunization_records_the_existing_patient_and_encounter_once(): void
    {
        $visit = $this->triagedVisit(RchService::ChildMonitoring);
        $this->seed(VaccineSeeder::class);
        $vaccine = Vaccine::query()->firstOrFail();
        $nurse = $this->staff('rch-nurse');
        $route = route('rch.visits.immunization', $visit);
        $this->actingAs($nurse)->get($route)->assertOk()->assertSee($visit->patient->fullName());
        $screen = Livewire::actingAs($nurse)->test(Administer::class, ['linkedVisit' => $visit->id])
            ->set('form.vaccine_id', $vaccine->id)->set('form.status', 'not_given')
            ->set('form.reason_not_given', 'Deferred at this visit')->call('save')->assertHasNoErrors();
        $screen->call('save')->assertHasNoErrors();

        $record = ImmunizationAdministration::query()->sole();
        $this->assertSame($visit->patient_id, $record->patient_id);
        $this->assertSame($visit->id, $record->visit_id);
        $this->assertSame(RchEncounter::query()->sole()->id, $record->rch_encounter_id);
        Livewire::actingAs($nurse)->test(GrowthMeasurement::class, ['linkedVisit' => $visit->id])
            ->call('save')->assertHasNoErrors();
        $this->assertSame($record->rch_child_id, ChildGrowthMeasurement::query()->sole()->rch_child_id);
        $this->assertSame($record->getRawOriginal(), $record->fresh()->getRawOriginal());
        $this->assertDatabaseCount('rch_children', 1);
        $this->assertDatabaseCount('patients', 1);
        $this->assertSame(1, $visit->invoice->items()->count());
    }

    public function test_pnc_saves_versions_and_history_across_new_and_returning_encounters(): void
    {
        $visit = $this->triagedVisit(RchService::Pnc);
        $nurse = $this->staff('rch-nurse');
        $route = route('rch.visits.pnc', $visit);
        $this->actingAs($nurse)->get($route)->assertOk()->assertSee($visit->patient->fullName());
        $screen = Livewire::actingAs($nurse)->test(PncScreen::class, ['linkedVisit' => $visit->id])
            ->set('form.delivery_date', today()->subDays(4)->toDateString())
            ->set('form.clinical_notes', 'First PNC clinical notes')
            ->set('form.newborn_condition', 'Newborn assessment documented')
            ->call('save')->assertHasNoErrors()
            ->set('form.breastfeeding_counselling', 'Counselling documented')
            ->call('save')->assertHasNoErrors()->call('save')->assertHasNoErrors();
        $record = PncVisit::query()->sole();
        $this->assertSame($visit->patient_id, $record->patient_id);
        $this->assertSame('Counselling documented', $record->breastfeeding_counselling);
        $this->assertDatabaseHas('activity_logs', ['event' => 'pnc_visit_created', 'subject_id' => $record->id]);
        $this->assertSame(1, ActivityLog::query()->where('event', 'pnc_visit_updated')->where('subject_id', $record->id)->count());
        $this->assertDatabaseCount('anc_visits', 0);

        Livewire::actingAs($nurse)->test(Encounter::class, ['visit' => $visit])->call('complete')->assertRedirect(route('rch.index'));
        $this->assertSame('completed', $record->refresh()->status);
        $screen->set('form.clinical_notes', 'Attempt to edit signed PNC')->call('save')->assertForbidden();

        $this->actingAs($this->admin);
        $return = app(ReceptionWorkflowService::class)->openReturningPatientVisit(
            $visit->patient, ['payer_type' => 'cash', 'is_primary' => true], $this->visitData(RchService::Pnc), [], $this->admin,
        )['visit'];
        $this->pay($return->invoice, 1000);
        $this->triage($return);
        Livewire::actingAs($nurse)->test(PncScreen::class, ['linkedVisit' => $return->id])
            ->assertSee('PNC history')->assertSee($route, false)
            ->set('form.clinical_notes', 'Second PNC notes')->call('save')->assertHasNoErrors();
        $this->assertSame(2, PncVisit::query()->where('patient_id', $visit->patient_id)->count());
        $this->assertDatabaseCount('patients', 1);
        $this->assertSame(1, $return->invoice->items()->count());

        $other = Facility::factory()->create();
        $this->actingAs($this->staff('rch-nurse', $other))->get($route)->assertNotFound();
        $this->actingAs($this->staff('receptionist'))->get($route)->assertForbidden();
    }

    public function test_pnc_saves_and_reopens_without_maternal_vitals(): void
    {
        $visit = $this->register(RchService::Pnc);
        $this->pay($visit->invoice, 1000);
        $this->triage($visit, []);
        $nurse = $this->staff('rch-nurse');
        $screen = Livewire::actingAs($nurse)->test(PncScreen::class, ['linkedVisit' => $visit->id])
            ->assertSet('form.maternal_vitals.temperature', null)
            ->assertSet('form.maternal_vitals.weight_kg', null)
            ->set('form.clinical_notes', 'Postnatal observations without recorded vitals')
            ->call('save')->assertHasNoErrors();

        $record = PncVisit::query()->sole();
        $this->assertIsArray($record->maternal_vitals);
        $this->assertSame([], array_filter($record->maternal_vitals, fn ($value) => $value !== null));
        $screen->set('form.maternal_vitals', [])->call('save')->assertHasNoErrors();
        $this->assertSame([], $record->refresh()->maternal_vitals);
        $this->assertNotNull($record->getRawOriginal('maternal_vitals'));

        Livewire::actingAs($nurse)->test(PncScreen::class, ['linkedVisit' => $visit->id])
            ->assertSet('form.maternal_vitals', [])
            ->assertSet('form.clinical_notes', 'Postnatal observations without recorded vitals');
        $this->assertDatabaseCount('pnc_visits', 1);
    }

    private function register(RchService $programme, ?string $key = null, array $payer = []): Visit
    {
        $this->actingAs($this->admin);

        return app(ReceptionWorkflowService::class)->registerNewPatientAndVisit(
            ['first_name' => 'RCH', 'last_name' => 'Patient', 'gender' => 'female',
                'age_years' => $programme === RchService::ChildMonitoring ? 3 : 24, 'patient_status' => 'active'],
            [...$payer, 'payer_type' => $payer['payer_type'] ?? 'cash', 'is_primary' => true],
            $this->visitData($programme, $key), [], $this->admin,
        )['visit'];
    }

    private function visitData(RchService $programme, ?string $key = null): array
    {
        return [
            'visit_type' => 'new_patient', 'destination_department_id' => $this->department('RCH')->id,
            'consultation_service_id' => $this->service($programme)->id, 'priority' => 'normal', 'source' => 'walk_in',
            'registration_idempotency_key' => $key ?? (string) Str::uuid(),
        ];
    }

    private function triagedVisit(RchService $programme): Visit
    {
        $visit = $this->register($programme);
        if ((float) $visit->invoice->balance_amount > 0) {
            $this->pay($visit->invoice, (int) $visit->invoice->balance_amount);
        }
        $this->triage($visit);

        return $visit->refresh();
    }

    private function triage(Visit $visit, array $vitals = ['temperature' => 36.5, 'weight_kg' => 50]): void
    {
        $nurse = $this->staff('nurse');
        $this->actingAs($nurse);
        $triage = app(TriageService::class);
        $assessment = $triage->startAssessment($visit->refresh(), $nurse);
        $triage->completeAssessment($assessment, $vitals, $nurse);
        $this->assertSame('RCH', $visit->refresh()->currentDepartment->code);
        $this->assertSame('RCH', $visit->currentQueue->department->code);
    }

    private function pay(Invoice $invoice, int $amount, ?array $allocation = null): void
    {
        $cashier = $this->staff('cashier');
        $this->actingAs($cashier);
        app(PaymentConfirmationService::class)->confirmPayment(
            $invoice->refresh(), PaymentMethod::query()->where('code', 'CASH')->firstOrFail(), $amount, $cashier,
            $allocation === null ? [] : ['allocations' => $allocation],
        );
    }

    private function staff(string $role, ?Facility $facility = null): User
    {
        $user = User::factory()->create();
        StaffProfile::factory()->create(['user_id' => $user->id, 'facility_id' => ($facility ?? $this->facility)->id]);
        $user->assignRole($role);

        return $user;
    }

    private function department(string $code): Department
    {
        return $this->facility->departments()->where('code', $code)->sole();
    }

    private function service(RchService $definition): Service
    {
        return Service::query()->where('facility_id', $this->facility->id)->where('code', $definition->value)->sole();
    }

    private function pricedService(string $name, string $code, string $category, string $type, int $price): Service
    {
        $service = Service::query()->create([
            'facility_id' => $this->facility->id,
            'service_category_id' => ServiceCategory::query()->where('facility_id', $this->facility->id)->where('code', $category)->sole()->id,
            'department_id' => $this->department($category)->id,
            'name' => $name, 'code' => $code, 'service_type' => $type, 'is_active' => true, 'requires_payment' => true,
        ]);
        $service->prices()->create([
            'facility_id' => $this->facility->id, 'payer_type' => 'cash', 'amount' => $price, 'currency' => 'TZS', 'is_active' => true,
        ]);

        return $service;
    }

    private function laboratoryService(string $name, int $price): Service
    {
        $service = $this->pricedService($name, Str::upper(Str::slug($name)), 'LAB', 'laboratory_test', $price);
        LaboratoryTest::factory()->create([
            'facility_id' => $this->facility->id, 'service_id' => $service->id, 'name' => $name, 'code' => $service->code,
            'result_type' => 'numeric', 'is_active' => true,
            'laboratory_test_category_id' => LaboratoryTestCategory::factory()->create(['facility_id' => $this->facility->id])->id,
            'specimen_type_id' => SpecimenType::factory()->create(['facility_id' => $this->facility->id])->id,
        ]);

        return $service;
    }

    private function medicine(string $name, string $code, StockLocation $location): Medicine
    {
        $this->actingAs($this->admin);
        $service = $this->pricedService($name, $code, 'PHA', 'medicine', 100);
        $unit = MedicineUnit::query()->where('facility_id', $this->facility->id)->firstOrFail();
        $medicine = Medicine::query()->create([
            'facility_id' => $this->facility->id, 'service_id' => $service->id, 'name' => $name, 'code' => $code,
            'purchase_unit_id' => $unit->id, 'dispensing_unit_id' => $unit->id, 'pack_size' => 1,
            'purchase_to_dispensing_factor' => 1, 'track_batch' => true, 'track_expiry' => true, 'is_active' => true,
        ]);
        $supplier = Supplier::query()->firstOrCreate(['facility_id' => $this->facility->id, 'code' => 'TEST-SUP'], [
            'name' => 'Test Supplier', 'phone_primary' => '0712000000', 'supplier_type' => 'pharmaceutical_wholesaler', 'is_active' => true,
        ]);
        app(StockReceivingService::class)->receive(['supplier_id' => $supplier->id, 'stock_location_id' => $location->id], [
            ['medicine_id' => $medicine->id, 'batch_number' => $code.'-late', 'expiry_date' => today()->addYears(2)->toDateString(), 'quantity_received' => 10, 'unit_cost' => 10],
            ['medicine_id' => $medicine->id, 'batch_number' => $code.'-early', 'expiry_date' => today()->addYear()->toDateString(), 'quantity_received' => 9, 'unit_cost' => 10],
        ], $this->admin);

        return $medicine;
    }
}
