<?php

namespace Tests\Feature\Laboratory;

use App\Enums\FacilityType;
use App\Enums\OwnershipType;
use App\Livewire\Billing\Invoices\Show as InvoiceShow;
use App\Livewire\Billing\Queue as BillingQueue;
use App\Livewire\Laboratory\Queue as LaboratoryQueue;
use App\Models\ClinicalEncounter;
use App\Models\CorporateAccount;
use App\Models\Department;
use App\Models\Facility;
use App\Models\InsuranceProvider;
use App\Models\Invoice;
use App\Models\LaboratoryOrder;
use App\Models\LaboratoryTest;
use App\Models\LaboratoryTestCategory;
use App\Models\Patient;
use App\Models\PatientPayerProfile;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServicePrice;
use App\Models\SpecimenType;
use App\Models\StaffProfile;
use App\Models\User;
use App\Models\Visit;
use App\Services\ClinicalEncounterService;
use App\Services\LaboratoryOrderItemDecisionService;
use App\Services\LaboratoryResultService;
use App\Services\LaboratorySampleService;
use App\Services\PaymentConfirmationService;
use Database\Seeders\BillingSettingsSeeder;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\LaboratoryTestCategorySeeder;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SpecimenTypeSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class LaboratoryPaymentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_cash_order_moves_from_doctor_to_billing_to_laboratory_after_full_payment(): void
    {
        [$admin, $facility] = $this->bootstrap();
        $doctor = $this->staffUser('doctor', $facility);
        $cashier = $this->staffUser('cashier', $facility);
        $technician = $this->staffUser('laboratory-technician', $facility);
        $encounter = $this->encounter($doctor, 'cash');
        $services = collect([
            $this->laboratoryService('CBC', 'CBC', 'cash', 12000, $admin),
            $this->laboratoryService('Malaria', 'MAL', 'cash', 8000, $admin),
        ]);

        $this->actingAs($doctor);
        $order = app(ClinicalEncounterService::class)->addLabOrder($encounter, [
            'service_ids' => $services->pluck('id')->all(),
            'clinical_notes' => 'Fever and malaise',
        ], $doctor);

        $invoice = Invoice::query()->where('visit_id', $encounter->visit_id)->firstOrFail();
        $this->assertSame('awaiting_payment', $order->status->value);
        $this->assertSame('pending', $order->payment_status->value);
        $this->assertSame(1, Invoice::query()->where('visit_id', $encounter->visit_id)->count());
        $this->assertSame(2, $order->items()->count());
        $this->assertSame(2, $invoice->items()->where('item_type', 'laboratory_test')->count());
        $this->assertSame('20000.00', $invoice->refresh()->balance_amount);
        $this->assertSame('20000.00', $invoice->total_amount);
        $this->assertDatabaseHas('activity_logs', ['event' => 'laboratory_charge_added']);
        $this->assertDatabaseHas('activity_logs', ['event' => 'laboratory_ordered']);
        $this->assertDatabaseHas('activity_logs', ['event' => 'laboratory_invoice_updated']);

        try {
            app(ClinicalEncounterService::class)->addLabOrder($encounter->refresh(), [
                'service_ids' => [$services->first()->id],
            ], $doctor);
            $this->fail('A duplicate active laboratory order was created.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('service_ids', $exception->errors());
        }
        $this->assertSame(1, LaboratoryOrder::query()->where('clinical_encounter_id', $encounter->id)->count());

        Livewire::actingAs($cashier)
            ->test(BillingQueue::class)
            ->assertSee($invoice->invoice_number)
            ->assertSee($encounter->visit->visit_number)
            ->assertSee('Laboratory')
            ->assertSee($doctor->name)
            ->assertSee($facility->name);

        Livewire::actingAs($technician)
            ->test(LaboratoryQueue::class)
            ->assertDontSee($order->order_number)
            ->set('tab', 'awaiting_payment')
            ->assertSee($order->order_number)
            ->assertDontSeeHtml('wire:click="openCollect('.$order->id.')"');

        $this->actingAs($cashier);
        $cash = PaymentMethod::query()->where('code', 'CASH')->firstOrFail();
        app(PaymentConfirmationService::class)->confirmPayment($invoice, $cash, 5000, $cashier);

        $this->assertSame('partial', $invoice->refresh()->payment_status);
        $this->assertSame('pending', $order->refresh()->payment_status->value);
        $this->assertSame('awaiting_payment', $order->status->value);

        app(PaymentConfirmationService::class)->confirmPayment($invoice->refresh(), $cash, 15000, $cashier);

        $this->assertSame('paid', $invoice->refresh()->payment_status);
        $this->assertSame('0.00', $invoice->balance_amount);
        $this->assertSame('paid', $order->refresh()->payment_status->value);
        $this->assertSame('ordered', $order->status->value);
        $this->assertSame(['ready_for_collection'], $order->items()->distinct()->pluck('status')->all());
        $this->assertDatabaseHas('activity_logs', ['event' => 'laboratory_payment_confirmed', 'subject_id' => $order->id]);
        $this->assertDatabaseHas('activity_logs', ['event' => 'laboratory_released', 'subject_id' => $order->id]);

        Livewire::actingAs($technician)
            ->test(LaboratoryQueue::class)
            ->assertSee($order->order_number);

        $this->actingAs($technician);
        $sample = app(LaboratorySampleService::class)->collectSample($order->refresh(), [
            'order_item_ids' => $order->items()->pluck('id')->all(),
        ], $technician, true);

        $this->assertSame('accepted', $sample->sample_status->value);
        $this->assertSame('processing', $order->refresh()->status->value);
        $this->assertDatabaseHas('activity_logs', ['event' => 'laboratory_processing_started', 'subject_id' => $order->id]);

        try {
            $this->actingAs($cashier);
            app(PaymentConfirmationService::class)->confirmPayment($invoice->refresh(), $cash, 1000, $cashier);
            $this->fail('A duplicate payment was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('amount', $exception->errors());
        }
        $this->assertSame(2, Payment::query()->where('invoice_id', $invoice->id)->count());
    }

    public function test_unpaid_sample_collection_requires_an_explicit_audited_override(): void
    {
        [$admin, $facility] = $this->bootstrap();
        $doctor = $this->staffUser('doctor', $facility);
        $technician = $this->staffUser('laboratory-technician', $facility);
        $encounter = $this->encounter($doctor, 'cash');
        $service = $this->laboratoryService('Urinalysis', 'UA', 'cash', 5000, $admin);
        $this->actingAs($doctor);
        $order = app(ClinicalEncounterService::class)->addLabOrder($encounter, ['service_ids' => [$service->id]], $doctor);

        $this->assertFalse($technician->can('laboratory.override-payment'));
        $this->actingAs($technician);
        try {
            app(LaboratorySampleService::class)->collectSample($order, [], $technician, true);
            $this->fail('An unpaid sample was collected without an override.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('payment', $exception->errors());
        }
        $this->assertDatabaseCount('laboratory_samples', 0);

        $technician->givePermissionTo('laboratory.override-payment');
        app(LaboratorySampleService::class)->collectSample($order->refresh(), [], $technician, true);

        $this->assertDatabaseHas('activity_logs', [
            'event' => 'laboratory_payment_override',
            'subject_id' => $order->id,
            'user_id' => $technician->id,
        ]);

        Livewire::actingAs($technician)
            ->test(LaboratoryQueue::class)
            ->set('tab', 'processing')
            ->assertDontSee($order->order_number)
            ->set('tab', 'awaiting_payment')
            ->assertSee($order->order_number)
            ->assertDontSee('Ingiza Matokeo');
    }

    public function test_unauthorized_users_cannot_confirm_payment_or_collect_samples(): void
    {
        [$admin, $facility] = $this->bootstrap();
        $doctor = $this->staffUser('doctor', $facility);
        $encounter = $this->encounter($doctor, 'cash');
        $service = $this->laboratoryService('HIV', 'HIV', 'cash', 7000, $admin);
        $this->actingAs($doctor);
        $order = app(ClinicalEncounterService::class)->addLabOrder($encounter, ['service_ids' => [$service->id]], $doctor);
        $invoice = Invoice::query()->where('visit_id', $encounter->visit_id)->firstOrFail();
        $cash = PaymentMethod::query()->where('code', 'CASH')->firstOrFail();
        $user = $this->staffUser(null, $facility);
        $user->givePermissionTo(['invoices.view', 'billing.view-invoice']);

        Livewire::actingAs($user)
            ->test(InvoiceShow::class, ['invoice' => $invoice])
            ->set('payment_method_id', $cash->id)
            ->set('amount', '7000')
            ->call('confirmPayment')
            ->assertForbidden();

        $this->actingAs($user);
        try {
            app(LaboratorySampleService::class)->collectSample($order, [], $user, true);
            $this->fail('An unauthorized user collected a sample.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('laboratory_samples', 0);
    }

    public function test_failed_and_partial_payments_do_not_release_the_order(): void
    {
        [$admin, $facility] = $this->bootstrap();
        $doctor = $this->staffUser('doctor', $facility);
        $cashier = $this->staffUser('cashier', $facility);
        $encounter = $this->encounter($doctor, 'cash');
        $service = $this->laboratoryService('Liver Function', 'LFT', 'cash', 10000, $admin);
        $this->actingAs($doctor);
        $order = app(ClinicalEncounterService::class)->addLabOrder($encounter, ['service_ids' => [$service->id]], $doctor);
        $invoice = Invoice::query()->where('visit_id', $encounter->visit_id)->firstOrFail();
        $mobile = PaymentMethod::query()->where('code', 'MPESA')->firstOrFail();

        $this->actingAs($cashier);
        try {
            app(PaymentConfirmationService::class)->confirmPayment($invoice, $mobile, 10000, $cashier);
            $this->fail('Payment without a required reference succeeded.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('transaction_reference', $exception->errors());
        }

        $this->assertDatabaseCount('payments', 0);
        $this->assertSame('pending', $order->refresh()->payment_status->value);
    }

    public function test_insurance_corporate_and_waived_orders_require_approved_coverage(): void
    {
        [$admin, $facility] = $this->bootstrap();
        $doctor = $this->staffUser('doctor', $facility);

        $provider = InsuranceProvider::query()->create([
            'facility_id' => $facility->id,
            'name' => 'Approved Insurance',
            'code' => 'APP-INS',
            'provider_type' => 'private_insurance',
            'claim_submission_method' => 'manual_report',
            'requires_pre_authorization' => false,
            'is_active' => true,
        ]);
        $insuranceEncounter = $this->encounter($doctor, 'insurance', [
            'insurance_provider_id' => $provider->id,
            'coverage_status' => 'active',
        ]);
        $insuranceService = $this->laboratoryService('Insurance CBC', 'ICBC', 'insurance', 9000, $admin, $provider->id);

        $this->actingAs($doctor);
        $insuranceOrder = app(ClinicalEncounterService::class)->addLabOrder($insuranceEncounter, ['service_ids' => [$insuranceService->id]], $doctor);
        $this->assertSame('covered', $insuranceOrder->payment_status->value);
        $this->assertSame('ordered', $insuranceOrder->status->value);

        $account = CorporateAccount::query()->create([
            'facility_id' => $facility->id,
            'name' => 'Approved Company',
            'code' => 'COMP',
            'credit_limit' => 100000,
            'payment_terms_days' => 30,
            'is_active' => true,
            'created_by' => $admin->id,
        ]);
        $corporateEncounter = $this->encounter($doctor, 'corporate', [
            'corporate_account_id' => $account->id,
            'coverage_status' => 'active',
        ]);
        $corporateService = $this->laboratoryService('Corporate CBC', 'CCBC', 'corporate', 11000, $admin, null, $account->id);
        $corporateOrder = app(ClinicalEncounterService::class)->addLabOrder($corporateEncounter, ['service_ids' => [$corporateService->id]], $doctor);
        $this->assertSame('covered', $corporateOrder->payment_status->value);

        $waivedEncounter = $this->encounter($doctor, 'exempted', ['coverage_status' => 'active']);
        $waivedService = $this->laboratoryService('Waived CBC', 'WCBC', 'exempted', 5000, $admin);
        $waivedOrder = app(ClinicalEncounterService::class)->addLabOrder($waivedEncounter, ['service_ids' => [$waivedService->id]], $doctor);
        $this->assertSame('waived', $waivedOrder->payment_status->value);

        $unapprovedEncounter = $this->encounter($doctor, 'insurance', [
            'insurance_provider_id' => $provider->id,
            'coverage_status' => 'pending_verification',
        ]);
        $unapprovedService = $this->laboratoryService('Unapproved Test', 'UNAPP', 'insurance', 4000, $admin, $provider->id);
        try {
            app(ClinicalEncounterService::class)->addLabOrder($unapprovedEncounter, ['service_ids' => [$unapprovedService->id]], $doctor);
            $this->fail('Unapproved insurance coverage bypassed payment.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('payer', $exception->errors());
        }
    }

    public function test_cross_facility_user_cannot_process_an_order(): void
    {
        [$admin, $facility] = $this->bootstrap();
        $doctor = $this->staffUser('doctor', $facility);
        $technician = $this->staffUser('laboratory-technician', $facility);
        $encounter = $this->encounter($doctor, 'cash');
        $service = $this->laboratoryService('Cross Facility Test', 'CROSS', 'cash', 3000, $admin);
        $this->actingAs($doctor);
        $order = app(ClinicalEncounterService::class)->addLabOrder($encounter, ['service_ids' => [$service->id]], $doctor);
        $technician->givePermissionTo('laboratory.override-payment');

        $otherFacility = Facility::query()->create([
            'name' => 'Other Facility', 'code' => 'OTHER', 'facility_type' => FacilityType::Dispensary,
            'ownership_type' => OwnershipType::Private, 'phone_primary' => '+255700000999', 'region' => 'Dar es Salaam',
            'district' => 'Ilala', 'ward' => 'Upanga', 'physical_address' => 'Upanga', 'setup_completed_at' => now(),
            'created_by' => $admin->id, 'updated_by' => $admin->id,
        ]);
        $otherUser = $this->staffUser(null, $otherFacility);
        $otherUser->givePermissionTo(['laboratory.collect-sample', 'laboratory.accept-sample', 'laboratory.override-payment']);

        $this->actingAs($otherUser);
        try {
            app(LaboratorySampleService::class)->collectSample($order, [], $otherUser, true);
            $this->fail('A cross-facility user processed the order.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_insurance_lab_item_decision_preserves_payer_history(): void
    {
        [$admin, $facility] = $this->bootstrap();
        $doctor = $this->staffUser('doctor', $facility);
        $technician = $this->staffUser('laboratory-technician', $facility);
        $provider = InsuranceProvider::query()->create([
            'facility_id' => $facility->id,
            'name' => 'Test insurer',
            'code' => 'INS-LAB-DECIDE',
            'provider_type' => 'private_insurance',
            'claim_submission_method' => 'manual_report',
            'requires_pre_authorization' => false,
            'is_active' => true,
        ]);
        $encounter = $this->encounter($doctor, 'insurance', [
            'insurance_provider_id' => $provider->id,
            'coverage_status' => 'active',
        ]);
        $service = $this->laboratoryService('Covered test', 'COVERED-DECIDE', 'insurance', 9000, $admin, $provider->id);
        $this->actingAs($doctor);
        $order = app(ClinicalEncounterService::class)->addLabOrder($encounter, ['service_ids' => [$service->id]], $doctor);
        $item = $order->items()->firstOrFail();
        $charge = $item->invoiceItem;
        $this->assertGreaterThan(0, (float) $charge->insurance_amount);
        $profileId = $charge->invoice->patient_payer_profile_id;
        $this->actingAs($technician);
        app(LaboratoryOrderItemDecisionService::class)->decide($item, 'not_performed', 'sample_unavailable', null, $technician);
        $this->assertSame('not_performed', $item->refresh()->status);
        $this->assertSame('cancelled', $charge->refresh()->status);
        $this->assertSame($profileId, $charge->invoice->refresh()->patient_payer_profile_id);
        $this->assertGreaterThan(0, (float) $charge->insurance_amount);
        $this->assertSame(0, $charge->invoice->payments()->count());
    }

    public function test_unprivileged_user_cannot_decide_a_lab_item(): void
    {
        [$admin, $facility] = $this->bootstrap();
        $doctor = $this->staffUser('doctor', $facility);
        $receptionist = $this->staffUser('receptionist', $facility);
        $encounter = $this->encounter($doctor, 'cash');
        $service = $this->laboratoryService('Protected test', 'PROTECTED-DECIDE', 'cash', 3000, $admin);
        $this->actingAs($doctor);
        $order = app(ClinicalEncounterService::class)->addLabOrder($encounter, ['service_ids' => [$service->id]], $doctor);
        $this->expectException(AuthorizationException::class);
        app(LaboratoryOrderItemDecisionService::class)->decide(
            $order->items()->firstOrFail(), 'cancelled', 'patient_declined', null, $receptionist,
        );
    }

    public function test_cross_facility_technician_cannot_decide_a_lab_item(): void
    {
        [$admin, $facility] = $this->bootstrap();
        $doctor = $this->staffUser('doctor', $facility);
        $encounter = $this->encounter($doctor, 'cash');
        $service = $this->laboratoryService('Isolated test', 'ISOLATED-DECIDE', 'cash', 3000, $admin);
        $this->actingAs($doctor);
        $order = app(ClinicalEncounterService::class)->addLabOrder($encounter, ['service_ids' => [$service->id]], $doctor);
        $otherFacility = Facility::query()->create([
            'name' => 'Isolated Facility', 'code' => 'ISOLATED', 'facility_type' => FacilityType::Dispensary,
            'ownership_type' => OwnershipType::Private, 'phone_primary' => '+255700000998', 'region' => 'Dar es Salaam',
            'district' => 'Ilala', 'ward' => 'Upanga', 'physical_address' => 'Upanga', 'setup_completed_at' => now(),
            'created_by' => $admin->id, 'updated_by' => $admin->id,
        ]);
        $foreignTechnician = $this->staffUser('laboratory-technician', $otherFacility);
        $this->expectException(AuthorizationException::class);
        app(LaboratoryOrderItemDecisionService::class)->decide(
            $order->items()->firstOrFail(), 'cancelled', 'patient_declined', null, $foreignTechnician,
        );
    }

    public function test_production_partial_cash_selection_does_not_open_unselected_lab_tests_until_second_payment(): void
    {
        [$admin, $facility] = $this->bootstrap();
        $doctor = $this->staffUser('doctor', $facility);
        $cashier = $this->staffUser('cashier', $facility);
        $technician = $this->staffUser('laboratory-technician', $facility);
        $encounter = $this->encounter($doctor, 'cash');
        $services = collect([
            $this->laboratoryService('Malaria', 'SELECT-MAL', 'cash', 5000, $admin),
            $this->laboratoryService('Hemoglobin', 'SELECT-HB', 'cash', 4000, $admin),
            $this->laboratoryService('Urinalysis', 'SELECT-UA', 'cash', 3000, $admin),
            $this->laboratoryService('RPR', 'SELECT-RPR', 'cash', 4000, $admin),
        ]);
        $this->actingAs($doctor);
        $order = app(ClinicalEncounterService::class)->addLabOrder($encounter, ['service_ids' => $services->pluck('id')->all()], $doctor);
        $items = $order->items()->orderBy('id')->get();
        $invoice = $items[0]->invoiceItem->invoice;
        $cash = PaymentMethod::query()->where('code', 'CASH')->firstOrFail();
        $selection = [$items[0]->invoice_item_id => '5000.00', $items[1]->invoice_item_id => '4000.00'];
        $this->actingAs($cashier);
        $screen = Livewire::test(InvoiceShow::class, ['invoice' => $invoice])->call('openPaymentModal')
            ->set('selectedItems', array_map('strval', array_keys($selection)))
            ->set('payment_method_id', $cash->id)->assertSet('amount', '9000.00')
            ->call('confirmPayment')->assertHasNoErrors()->assertSet('showPaymentModal', false);
        $payment = $invoice->payments()->sole();
        $retry = app(PaymentConfirmationService::class)->confirmPayment($invoice, $cash, 9000, $cashier, [
            'allocations' => $selection, 'idempotency_key' => $payment->idempotency_key,
        ]);
        $this->assertSame($payment->id, $retry->id);
        $this->assertSame(2, $payment->allocations()->count());
        $firstAllocations = $payment->allocations()->get()->toArray();
        $firstPayment = $payment->fresh()->getAttributes();
        $this->assertSame('9000.00', $invoice->refresh()->paid_amount);
        $this->assertSame('7000.00', $invoice->balance_amount);
        $this->assertSame('16000.00', $invoice->total_amount);
        $this->assertSame('partial', $invoice->payment_status);
        foreach ($items as $index => $item) {
            $this->assertSame($index < 2, $item->isFinanciallyCleared());
            $this->assertSame($index < 2 ? 'ready_for_collection' : 'awaiting_payment', $item->refresh()->status);
            $this->assertSame($index < 2 ? $selection[$item->invoice_item_id] : '0.00', $item->invoiceItem->refresh()->paid_amount);
        }
        $screen->call('openPaymentModal')->assertSee('PAID / CLEARED')->assertSee('UNPAID')
            ->assertSet('selectedItems', [(string) $items[2]->invoice_item_id, (string) $items[3]->invoice_item_id]);
        Livewire::actingAs($technician)->test(LaboratoryQueue::class)
            ->assertSee('Malaria')->assertSee('Hemoglobin')->assertDontSee('Urinalysis')->assertDontSee('RPR')
            ->set('tab', 'awaiting_payment')->assertSee('Urinalysis')->assertSee('RPR')
            ->assertDontSeeHtml('wire:click="openCollect('.$order->id.', '.$items[2]->id.')"');
        $this->actingAs($technician);
        foreach (['collect', 'result'] as $action) {
            try {
                if ($action === 'collect') {
                    app(LaboratorySampleService::class)->collectSample($order, ['order_item_ids' => [$items[2]->id]], $technician, true);
                } else {
                    app(LaboratoryResultService::class)->createDraft($items[2], $technician);
                }
                $this->fail('An unselected test was processed.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('payment', $exception->errors());
            }
        }
        $technician->givePermissionTo('laboratory.override-payment');
        try {
            app(LaboratorySampleService::class)->collectSample($order, ['order_item_ids' => [$items[3]->id]], $technician, true);
            $this->fail('An override bypassed explicit cashier selection.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('payment', $exception->errors());
        }
        app(LaboratorySampleService::class)->collectSample($order, ['order_item_ids' => [$items[0]->id, $items[1]->id]], $technician, true);
        $firstSample = $items[0]->refresh()->sample_id;
        $this->actingAs($cashier);
        app(PaymentConfirmationService::class)->confirmPayment($invoice, $cash, 7000, $cashier, [
            'allocations' => [$items[2]->invoice_item_id => 3000, $items[3]->invoice_item_id => 4000],
            'idempotency_key' => (string) Str::uuid(),
        ]);
        $this->assertSame($firstAllocations, $payment->allocations()->get()->toArray());
        $this->assertSame($firstPayment, $payment->fresh()->getAttributes());
        $this->assertSame('16000.00', $invoice->refresh()->paid_amount);
        $this->assertSame('0.00', $invoice->balance_amount);
        $this->assertSame('paid', $invoice->payment_status);
        $this->assertSame($firstSample, $items[0]->refresh()->sample_id);
        $this->assertSame('sample_accepted', $items[0]->status);
        $this->assertTrue($items->every(fn ($item) => $item->isFinanciallyCleared()));
        $this->assertSame(2, $invoice->payments()->count());
        $this->assertDatabaseCount('payment_allocations', 4);
        $this->assertDatabaseCount('laboratory_orders', 1);
        $this->assertDatabaseCount('invoice_items', 4);
        $this->assertDatabaseCount('invoices', 1);
        $this->assertSame(1, $encounter->visit->queues()->whereHas('department', fn ($q) => $q->where('code', 'LAB'))->count());
        $this->actingAs($technician);
        app(LaboratorySampleService::class)->collectSample($order, ['order_item_ids' => [$items[2]->id, $items[3]->id]], $technician, true);
    }

    public function test_selective_allocations_reject_invalid_items_amounts_and_changed_retry_without_writes(): void
    {
        [$admin, $facility] = $this->bootstrap();
        $doctor = $this->staffUser('doctor', $facility);
        $cashier = $this->staffUser('cashier', $facility);
        $encounter = $this->encounter($doctor, 'cash');
        $service = $this->laboratoryService('Selection validation', 'SELECT-VALID', 'cash', 5000, $admin);
        $this->actingAs($doctor);
        $order = app(ClinicalEncounterService::class)->addLabOrder($encounter, ['service_ids' => [$service->id]], $doctor);
        $charge = $order->items()->sole()->invoiceItem;
        $invoice = $charge->invoice;
        $otherEncounter = $this->encounter($doctor, 'cash');
        $otherOrder = app(ClinicalEncounterService::class)->addLabOrder($otherEncounter, ['service_ids' => [$service->id]], $doctor);
        $otherCharge = $otherOrder->items()->sole()->invoiceItem;
        $cash = PaymentMethod::query()->where('code', 'CASH')->firstOrFail();
        $this->actingAs($cashier);
        foreach ([[], [$charge->id => 0], [$charge->id => -100], [$charge->id => 1000.001], [$charge->id => 999], [$otherCharge->id => 1000], [999999 => 1000]] as $allocations) {
            try {
                app(PaymentConfirmationService::class)->confirmPayment($invoice, $cash, 1000, $cashier, ['allocations' => $allocations]);
                $this->fail('Invalid allocation accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('allocations', $exception->errors());
            }
        }
        // A forged foreign-facility item attached to this invoice is also rejected.
        $foreignFacility = $facility->replicate();
        $foreignFacility->code = 'FOREIGN-SELECT';
        $foreignFacility->save();
        $otherCharge->update(['invoice_id' => $invoice->id, 'facility_id' => $foreignFacility->id]);
        try {
            app(PaymentConfirmationService::class)->confirmPayment($invoice, $cash, 1000, $cashier, ['allocations' => [$otherCharge->id => 1000]]);
            $this->fail('Cross-facility allocation accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('allocations', $exception->errors());
        }
        $otherCharge->update(['facility_id' => $facility->id]);
        $charge->update(['status' => 'cancelled']);
        try {
            app(PaymentConfirmationService::class)->confirmPayment($invoice, $cash, 1000, $cashier, ['allocations' => [$charge->id => 1000]]);
            $this->fail('Cancelled allocation accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('allocations', $exception->errors());
        }
        $charge->update(['status' => 'pending']);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('payment_allocations', 0);
        $key = (string) Str::uuid();
        app(PaymentConfirmationService::class)->confirmPayment($invoice, $cash, 4000, $cashier, ['allocations' => [$charge->id => 4000], 'idempotency_key' => $key]);
        foreach ([['amount' => 2000, 'data' => ['allocations' => [$charge->id => 2000]]], ['amount' => 4000, 'data' => ['allocations' => [$otherCharge->id => 4000], 'idempotency_key' => $key]]] as $attempt) {
            try {
                app(PaymentConfirmationService::class)->confirmPayment($invoice, $cash, $attempt['amount'], $cashier, $attempt['data']);
                $this->fail('Stale balance or changed retry accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('allocations', $exception->errors());
            }
        }
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame('4000.00', $charge->refresh()->paid_amount);
    }

    public function test_free_lab_item_is_ready_without_payment_on_a_mixed_unpaid_invoice(): void
    {
        [$admin, $facility] = $this->bootstrap();
        $doctor = $this->staffUser('doctor', $facility);
        $encounter = $this->encounter($doctor, 'cash');
        $free = $this->laboratoryService('Free lab', 'FREE-SELECT', 'cash', 0, $admin);
        $paid = $this->laboratoryService('Paid lab', 'PAID-SELECT', 'cash', 5000, $admin);
        $this->actingAs($doctor);
        $order = app(ClinicalEncounterService::class)->addLabOrder($encounter, ['service_ids' => [$free->id, $paid->id]], $doctor);
        $this->assertTrue($order->items()->where('service_id', $free->id)->sole()->isFinanciallyCleared());
        $this->assertFalse($order->items()->where('service_id', $paid->id)->sole()->isFinanciallyCleared());
        $this->assertSame('ordered', $order->status->value);
        $this->assertDatabaseCount('payments', 0);
    }

    /** @return array{User, Facility} */
    private function bootstrap(): array
    {
        $admin = User::factory()->superAdmin()->create(['email' => fake()->unique()->safeEmail()]);
        $facility = Facility::query()->create([
            'name' => 'Laboratory Workflow Facility', 'code' => 'LWF', 'facility_type' => FacilityType::Dispensary,
            'ownership_type' => OwnershipType::Private, 'phone_primary' => '+255700000000', 'region' => 'Dar es Salaam',
            'district' => 'Kinondoni', 'ward' => 'Kijitonyama', 'physical_address' => 'Kijitonyama', 'setup_completed_at' => now(),
            'created_by' => $admin->id, 'updated_by' => $admin->id,
        ]);
        $this->seed([
            PermissionSeeder::class, RoleSeeder::class, DepartmentSeeder::class, RolePermissionSeeder::class,
            PaymentMethodSeeder::class, BillingSettingsSeeder::class, LaboratoryTestCategorySeeder::class, SpecimenTypeSeeder::class,
        ]);

        return [$admin, $facility];
    }

    private function staffUser(?string $role, Facility $facility): User
    {
        $user = User::factory()->create();
        StaffProfile::factory()->create(['user_id' => $user->id, 'facility_id' => $facility->id]);
        if ($role) {
            $user->assignRole($role);
        }

        return $user;
    }

    private function encounter(User $doctor, string $payerType, array $profileData = []): ClinicalEncounter
    {
        $facility = Facility::query()->firstOrFail();
        $patient = Patient::factory()->create(['facility_id' => $facility->id, 'created_by' => $doctor->id]);
        $profile = null;
        if ($profileData !== []) {
            $profile = PatientPayerProfile::query()->create([
                'patient_id' => $patient->id,
                'facility_id' => $facility->id,
                'payer_type' => $payerType,
                'valid_from' => today()->subDay(),
                'valid_to' => today()->addMonth(),
                'is_primary' => true,
                'created_by' => $doctor->id,
                ...$profileData,
            ]);
        }
        $opd = Department::query()->where('facility_id', $facility->id)->where('code', 'OPD')->firstOrFail();
        $visit = Visit::query()->create([
            'facility_id' => $facility->id, 'patient_id' => $patient->id,
            'visit_number' => 'VIS-LAB-'.fake()->unique()->numerify('######'), 'visit_type' => 'new_patient',
            'payer_type' => $payerType, 'patient_payer_profile_id' => $profile?->id,
            'destination_department_id' => $opd->id, 'current_department_id' => $opd->id,
            'visit_status' => 'in_consultation', 'priority' => 'normal', 'registered_at' => now(), 'created_by' => $doctor->id,
        ]);

        return ClinicalEncounter::query()->create([
            'facility_id' => $facility->id, 'patient_id' => $patient->id, 'visit_id' => $visit->id,
            'department_id' => $opd->id, 'encounter_type' => 'opd',
            'encounter_number' => 'ENC-LAB-'.fake()->unique()->numerify('######'),
            'provider_user_id' => $doctor->id, 'started_at' => now(), 'status' => 'in_progress', 'created_by' => $doctor->id,
        ]);
    }

    private function laboratoryService(string $name, string $code, string $payerType, int $amount, User $actor, ?int $providerId = null, ?int $accountId = null): Service
    {
        $facility = Facility::query()->firstOrFail();
        $laboratory = Department::query()->where('facility_id', $facility->id)->where('code', 'LAB')->firstOrFail();
        $category = ServiceCategory::query()->firstOrCreate(
            ['facility_id' => $facility->id, 'code' => 'LAB-SERVICES'],
            ['name' => 'Laboratory Services', 'category_type' => 'laboratory', 'is_active' => true, 'created_by' => $actor->id],
        );
        $service = Service::query()->create([
            'facility_id' => $facility->id, 'service_category_id' => $category->id, 'department_id' => $laboratory->id,
            'name' => $name, 'code' => $code, 'service_type' => 'laboratory_test', 'requires_payment' => true,
            'is_active' => true, 'created_by' => $actor->id,
        ]);
        ServicePrice::query()->create([
            'facility_id' => $facility->id, 'service_id' => $service->id, 'payer_type' => $payerType,
            'insurance_provider_id' => $providerId, 'corporate_account_id' => $accountId,
            'amount' => $amount, 'currency' => 'TZS', 'is_active' => true, 'created_by' => $actor->id,
        ]);
        LaboratoryTest::query()->create([
            'facility_id' => $facility->id, 'service_id' => $service->id,
            'laboratory_test_category_id' => LaboratoryTestCategory::query()->where('facility_id', $facility->id)->value('id'),
            'specimen_type_id' => SpecimenType::query()->where('facility_id', $facility->id)->value('id'),
            'name' => $name, 'code' => $code, 'result_type' => 'numeric', 'is_active' => true, 'reportable' => true,
            'created_by' => $actor->id,
        ]);

        return $service;
    }
}
