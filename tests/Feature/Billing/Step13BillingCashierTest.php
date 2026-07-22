<?php

namespace Tests\Feature\Billing;

use App\Enums\FacilityType;
use App\Enums\OwnershipType;
use App\Livewire\Billing\Cashier\Dashboard as CashierDashboard;
use App\Livewire\Billing\Cashier\Sessions as CashierSessions;
use App\Livewire\Billing\Dashboard as BillingDashboard;
use App\Livewire\Billing\Invoices\Index as InvoiceIndex;
use App\Livewire\Billing\Invoices\Show as InvoiceShow;
use App\Livewire\Billing\Queue as BillingQueue;
use App\Livewire\Billing\Reports\Index as BillingReport;
use App\Livewire\Billing\Settings\PaymentMethods;
use App\Livewire\Billing\Settings\Preferences;
use App\Models\CashierSession;
use App\Models\Department;
use App\Models\Facility;
use App\Models\FacilitySetting;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Patient;
use App\Models\PatientQueue;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Permission;
use App\Models\User;
use App\Models\Visit;
use App\Services\CashierSessionService;
use App\Services\PaymentConfirmationService;
use Database\Seeders\BillingSettingsSeeder;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class Step13BillingCashierTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_and_unauthorized_users_cannot_access_billing(): void
    {
        $this->get(route('billing.index'))->assertRedirect(route('login'));

        $this->bootstrappedFacility();
        $this->actingAs(User::factory()->create())->get(route('billing.index'))->assertForbidden();
    }

    public function test_billing_seeders_are_idempotent_and_settings_pages_render(): void
    {
        $admin = $this->bootstrappedFacility();
        $this->seed([PaymentMethodSeeder::class, BillingSettingsSeeder::class]);
        $this->seed([PaymentMethodSeeder::class, BillingSettingsSeeder::class]);

        $this->assertSame(1, PaymentMethod::query()->whereNull('facility_id')->where('code', 'CASH')->count());

        Livewire::actingAs($admin)->test(PaymentMethods::class)->assertOk();
        Livewire::actingAs($admin)->test(Preferences::class)->assertOk();
        Livewire::actingAs($admin)->test(BillingDashboard::class)->assertOk();
        Livewire::actingAs($admin)->test(BillingQueue::class)->assertOk();
        Livewire::actingAs($admin)->test(InvoiceIndex::class)->assertOk();
        Livewire::actingAs($admin)->test(CashierDashboard::class)->assertOk();
        Livewire::actingAs($admin)->test(CashierSessions::class)->assertOk();
        Livewire::actingAs($admin)->test(BillingReport::class)->assertOk();
    }

    public function test_payment_method_modal_prevents_duplicate_facility_code(): void
    {
        $admin = $this->bootstrappedFacility();

        Livewire::actingAs($admin)
            ->test(PaymentMethods::class)
            ->call('create')
            ->set('form.name', 'Test Wallet')
            ->set('form.code', 'TEST_WALLET')
            ->set('form.type', 'mobile_money')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('payment_methods', ['facility_id' => currentFacility()->id, 'code' => 'TEST_WALLET']);

        Livewire::actingAs($admin)
            ->test(PaymentMethods::class)
            ->call('create')
            ->set('form.name', 'Duplicate Wallet')
            ->set('form.code', 'TEST_WALLET')
            ->set('form.type', 'mobile_money')
            ->call('save')
            ->assertHasErrors(['form.code']);
    }

    public function test_cashier_session_payment_receipt_and_route_access_work(): void
    {
        $admin = $this->bootstrappedFacility();
        $this->actingAs($admin);

        $patient = Patient::factory()->create(['facility_id' => currentFacility()->id, 'created_by' => $admin->id]);
        $invoice = Invoice::query()->create([
            'facility_id' => currentFacility()->id,
            'patient_id' => $patient->id,
            'invoice_number' => 'INV-BIL-001',
            'payer_type' => 'cash',
            'invoice_status' => 'pending',
            'subtotal' => 10000,
            'patient_amount' => 10000,
            'total_amount' => 10000,
            'balance_amount' => 10000,
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
            'unit_price' => 10000,
            'gross_amount' => 10000,
            'payer_amount' => 10000,
            'patient_amount' => 10000,
            'insurance_amount' => 0,
            'total_amount' => 10000,
            'net_amount' => 10000,
            'status' => 'pending',
            'created_by' => $admin->id,
        ]);

        $session = app(CashierSessionService::class)->openSession($admin, 5000);
        $method = PaymentMethod::query()->where('code', 'CASH')->firstOrFail();
        $payment = app(PaymentConfirmationService::class)->confirmPayment($invoice, $method, 4000, $admin);

        $this->assertInstanceOf(CashierSession::class, $session);
        $this->assertSame('partial', $invoice->refresh()->payment_status);
        $this->assertDatabaseHas('receipts', ['payment_id' => $payment->id, 'amount' => 4000]);
        $this->assertDatabaseHas('payment_allocations', ['payment_id' => $payment->id, 'invoice_id' => $invoice->id]);

        Livewire::actingAs($admin)->test(InvoiceShow::class, ['invoice' => $invoice->refresh()])->assertOk();
        $this->get(route('billing.invoices.print', $invoice))->assertOk();
        $this->get(route('billing.receipts.print', $payment->receipt))->assertOk();
        $this->get(route('cashier.sessions.show', $session))->assertOk();
        $this->get(route('cashier.sessions.print', $session))->assertOk();
        $this->get(route('reports.billing.export', ['type' => 'collections']))->assertOk();
    }

    public function test_overpayment_and_missing_reference_are_blocked(): void
    {
        $admin = $this->bootstrappedFacility();
        $this->actingAs($admin);
        app(CashierSessionService::class)->openSession($admin, 0);

        $patient = Patient::factory()->create(['facility_id' => currentFacility()->id, 'created_by' => $admin->id]);
        $invoice = Invoice::query()->create([
            'facility_id' => currentFacility()->id,
            'patient_id' => $patient->id,
            'invoice_number' => 'INV-BIL-002',
            'payer_type' => 'cash',
            'invoice_status' => 'pending',
            'subtotal' => 1000,
            'patient_amount' => 1000,
            'total_amount' => 1000,
            'balance_amount' => 1000,
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
            'unit_price' => 1000,
            'gross_amount' => 1000,
            'payer_amount' => 1000,
            'patient_amount' => 1000,
            'insurance_amount' => 0,
            'total_amount' => 1000,
            'net_amount' => 1000,
            'status' => 'pending',
            'created_by' => $admin->id,
        ]);

        $cash = PaymentMethod::query()->where('code', 'CASH')->firstOrFail();
        $mobile = PaymentMethod::query()->where('code', 'MPESA')->firstOrFail();

        try {
            app(PaymentConfirmationService::class)->confirmPayment($invoice, $cash, 2000, $admin);
            $this->fail('Overpayment was not blocked.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('amount', $exception->errors());
        }

        try {
            app(PaymentConfirmationService::class)->confirmPayment($invoice, $mobile, 500, $admin);
            $this->fail('Missing reference payment was not blocked.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('transaction_reference', $exception->errors());
        }
    }

    public function test_invoice_receive_payment_modal_submits_to_confirm_payment(): void
    {
        $admin = $this->bootstrappedFacility();
        $invoice = $this->createCashInvoice($admin, 'INV-BIL-MODAL', 10000);

        Livewire::actingAs($admin)
            ->test(InvoiceShow::class, ['invoice' => $invoice])
            ->call('openPaymentModal')
            ->assertSet('showPaymentModal', true)
            ->assertSeeHtml('wire:submit="confirmPayment"')
            ->assertSeeHtml('type="submit"')
            ->assertSee('Confirm Payment');
    }

    public function test_invoice_confirm_payment_creates_payment_allocation_receipt_and_refreshes_modal(): void
    {
        $admin = $this->bootstrappedFacility();
        $this->actingAs($admin);
        app(CashierSessionService::class)->openSession($admin, 0);

        $invoice = $this->createCashInvoice($admin, 'INV-BIL-LW-001', 10000);
        $cash = PaymentMethod::query()->where('code', 'CASH')->firstOrFail();

        Livewire::actingAs($admin)
            ->test(InvoiceShow::class, ['invoice' => $invoice])
            ->call('openPaymentModal')
            ->set('payment_method_id', $cash->id)
            ->set('amount', '10000')
            ->call('confirmPayment')
            ->assertHasNoErrors()
            ->assertSet('showPaymentModal', false);

        $this->assertDatabaseHas('payments', [
            'invoice_id' => $invoice->id,
            'payment_method_id' => $cash->id,
            'amount' => 10000,
            'status' => 'confirmed',
        ]);
        $this->assertDatabaseHas('payment_allocations', ['invoice_id' => $invoice->id, 'allocated_amount' => 10000]);
        $this->assertDatabaseHas('receipts', ['invoice_id' => $invoice->id, 'amount' => 10000]);
        $this->assertSame('paid', $invoice->refresh()->payment_status);
        $this->assertSame('0.00', $invoice->balance_amount);
    }

    public function test_full_payment_routes_opd_to_triage_when_destination_requires_triage(): void
    {
        $admin = $this->bootstrappedFacility();
        $this->actingAs($admin);

        $opd = Department::query()->forCurrentFacility()->where('code', 'OPD')->firstOrFail();
        $opd->update(['queue_enabled' => true, 'requires_triage' => true]);
        $triage = Department::query()->forCurrentFacility()->where('code', 'TRI')->firstOrFail();
        $invoice = $this->createCashInvoiceForVisit($admin, 'INV-BIL-TRIAGE-ON', 10000, $opd);

        app(PaymentConfirmationService::class)->confirmPayment($invoice, PaymentMethod::query()->where('code', 'CASH')->firstOrFail(), 10000, $admin);

        $this->assertDatabaseHas('patient_queues', ['visit_id' => $invoice->visit_id, 'department_id' => $triage->id, 'queue_status' => 'waiting']);
        $this->assertDatabaseHas('patient_queues', ['visit_id' => $invoice->visit_id, 'department_id' => Department::query()->forCurrentFacility()->where('code', 'BIL')->value('id'), 'queue_status' => 'transferred']);
        $this->assertSame('in_progress', Visit::query()->findOrFail($invoice->visit_id)->visit_status->value);
    }

    public function test_full_payment_routes_opd_directly_when_triage_is_disabled(): void
    {
        $admin = $this->bootstrappedFacility();
        $this->actingAs($admin);

        $opd = Department::query()->forCurrentFacility()->where('code', 'OPD')->firstOrFail();
        $opd->update(['queue_enabled' => true, 'requires_triage' => false]);
        $triage = Department::query()->forCurrentFacility()->where('code', 'TRI')->firstOrFail();
        $invoice = $this->createCashInvoiceForVisit($admin, 'INV-BIL-TRIAGE-OFF', 10000, $opd);

        app(PaymentConfirmationService::class)->confirmPayment($invoice, PaymentMethod::query()->where('code', 'CASH')->firstOrFail(), 10000, $admin);

        $this->assertDatabaseHas('patient_queues', ['visit_id' => $invoice->visit_id, 'department_id' => $opd->id, 'queue_status' => 'waiting']);
        $this->assertDatabaseMissing('patient_queues', ['visit_id' => $invoice->visit_id, 'department_id' => $triage->id, 'queue_status' => 'waiting']);
        $this->assertSame('in_progress', Visit::query()->findOrFail($invoice->visit_id)->visit_status->value);
    }

    public function test_cash_patient_full_payment_changes_waiting_visit_to_in_progress(): void
    {
        $admin = $this->bootstrappedFacility();
        $this->actingAs($admin);

        $opd = Department::query()->forCurrentFacility()->where('code', 'OPD')->firstOrFail();
        $opd->update(['queue_enabled' => true, 'requires_triage' => false]);
        $invoice = $this->createCashInvoiceForVisit($admin, 'INV-BIL-WAITING-INPROG', 10000, $opd, 'waiting');

        app(PaymentConfirmationService::class)->confirmPayment($invoice, PaymentMethod::query()->where('code', 'CASH')->firstOrFail(), 10000, $admin);

        $visit = Visit::query()->findOrFail($invoice->visit_id);
        $this->assertSame('in_progress', $visit->visit_status->value);
        $this->assertSame($opd->id, $visit->current_department_id);
        $this->assertDatabaseHas('patient_queues', ['visit_id' => $visit->id, 'department_id' => $opd->id, 'queue_status' => 'waiting']);
    }

    public function test_partial_payment_does_not_release_patient_from_billing(): void
    {
        $admin = $this->bootstrappedFacility();
        $this->actingAs($admin);

        $opd = Department::query()->forCurrentFacility()->where('code', 'OPD')->firstOrFail();
        $opd->update(['queue_enabled' => true, 'requires_triage' => true]);
        $invoice = $this->createCashInvoiceForVisit($admin, 'INV-BIL-PARTIAL-STAYS', 10000, $opd, 'waiting');

        app(PaymentConfirmationService::class)->confirmPayment($invoice, PaymentMethod::query()->where('code', 'CASH')->firstOrFail(), 4000, $admin);

        $this->assertDatabaseHas('patient_queues', ['visit_id' => $invoice->visit_id, 'department_id' => Department::query()->forCurrentFacility()->where('code', 'BIL')->value('id'), 'queue_status' => 'waiting']);
        $this->assertSame('waiting', Visit::query()->findOrFail($invoice->visit_id)->visit_status->value);
    }

    public function test_dental_uses_its_own_triage_setting_after_payment(): void
    {
        $admin = $this->bootstrappedFacility();
        $this->actingAs($admin);

        Department::query()->forCurrentFacility()->where('code', 'OPD')->firstOrFail()->update(['requires_triage' => true]);
        $dental = Department::query()->forCurrentFacility()->where('code', 'DEN')->firstOrFail();
        $dental->update(['queue_enabled' => true, 'requires_triage' => false]);
        $triage = Department::query()->forCurrentFacility()->where('code', 'TRI')->firstOrFail();
        $invoice = $this->createCashInvoiceForVisit($admin, 'INV-BIL-DENTAL', 10000, $dental);

        app(PaymentConfirmationService::class)->confirmPayment($invoice, PaymentMethod::query()->where('code', 'CASH')->firstOrFail(), 10000, $admin);

        $this->assertDatabaseHas('patient_queues', ['visit_id' => $invoice->visit_id, 'department_id' => $dental->id, 'queue_status' => 'waiting']);
        $this->assertDatabaseMissing('patient_queues', ['visit_id' => $invoice->visit_id, 'department_id' => $triage->id, 'queue_status' => 'waiting']);
        $this->assertSame('in_progress', Visit::query()->findOrFail($invoice->visit_id)->visit_status->value);
    }

    public function test_laboratory_full_payment_routes_to_laboratory_in_progress_without_triage(): void
    {
        $admin = $this->bootstrappedFacility();
        $this->actingAs($admin);

        $laboratory = Department::query()->forCurrentFacility()->where('code', 'LAB')->firstOrFail();
        $laboratory->update(['queue_enabled' => true, 'requires_triage' => false]);
        $triage = Department::query()->forCurrentFacility()->where('code', 'TRI')->firstOrFail();
        $invoice = $this->createCashInvoiceForVisit($admin, 'INV-BIL-LAB', 10000, $laboratory, 'waiting');

        app(PaymentConfirmationService::class)->confirmPayment($invoice, PaymentMethod::query()->where('code', 'CASH')->firstOrFail(), 10000, $admin);

        $this->assertDatabaseHas('patient_queues', ['visit_id' => $invoice->visit_id, 'department_id' => $laboratory->id, 'queue_status' => 'waiting']);
        $this->assertDatabaseMissing('patient_queues', ['visit_id' => $invoice->visit_id, 'department_id' => $triage->id, 'queue_status' => 'waiting']);
        $this->assertSame('in_progress', Visit::query()->findOrFail($invoice->visit_id)->visit_status->value);
    }

    public function test_rch_uses_its_own_triage_setting_after_payment(): void
    {
        $admin = $this->bootstrappedFacility();
        $this->actingAs($admin);

        $rch = Department::query()->forCurrentFacility()->where('code', 'RCH')->firstOrFail();
        $rch->update(['queue_enabled' => true, 'requires_triage' => true]);
        $triage = Department::query()->forCurrentFacility()->where('code', 'TRI')->firstOrFail();
        $invoice = $this->createCashInvoiceForVisit($admin, 'INV-BIL-RCH', 10000, $rch);

        app(PaymentConfirmationService::class)->confirmPayment($invoice, PaymentMethod::query()->where('code', 'CASH')->firstOrFail(), 10000, $admin);

        $this->assertDatabaseHas('patient_queues', ['visit_id' => $invoice->visit_id, 'department_id' => $triage->id, 'queue_status' => 'waiting']);
        $this->assertSame('in_progress', Visit::query()->findOrFail($invoice->visit_id)->visit_status->value);
    }

    public function test_insurance_payment_release_uses_existing_destination_workflow(): void
    {
        $admin = $this->bootstrappedFacility();
        $this->actingAs($admin);

        $opd = Department::query()->forCurrentFacility()->where('code', 'OPD')->firstOrFail();
        $opd->update(['queue_enabled' => true, 'requires_triage' => false]);
        $invoice = $this->createCashInvoiceForVisit($admin, 'INV-BIL-INSURANCE', 10000, $opd, 'waiting', 'insurance');

        app(PaymentConfirmationService::class)->confirmPayment($invoice, PaymentMethod::query()->where('code', 'CASH')->firstOrFail(), 10000, $admin);

        $visit = Visit::query()->findOrFail($invoice->visit_id);
        $this->assertSame('insurance', $visit->payer_type->value);
        $this->assertSame('in_progress', $visit->visit_status->value);
        $this->assertSame($opd->id, $visit->current_department_id);
    }

    public function test_invoice_confirm_payment_validation_errors_remain_visible_in_modal(): void
    {
        $admin = $this->bootstrappedFacility();
        $invoice = $this->createCashInvoice($admin, 'INV-BIL-LW-002', 10000);

        Livewire::actingAs($admin)
            ->test(InvoiceShow::class, ['invoice' => $invoice])
            ->call('openPaymentModal')
            ->set('amount', '')
            ->call('confirmPayment')
            ->assertHasErrors(['payment_method_id', 'amount'])
            ->assertSet('showPaymentModal', true)
            ->assertSee('The payment method id field is required.')
            ->assertSee('The amount field is required.');
    }

    public function test_authorized_cashier_can_open_session_with_shift_and_drawer(): void
    {
        $admin = $this->bootstrappedFacility();

        Livewire::actingAs($admin)
            ->test(CashierSessions::class)
            ->call('create')
            ->set('openForm.shift', 'afternoon')
            ->set('openForm.opening_float', '2500')
            ->set('openForm.cash_drawer', 'Main Counter')
            ->call('openSession')
            ->assertHasNoErrors()
            ->assertSet('showOpen', false);

        $this->assertDatabaseHas('cashier_sessions', [
            'facility_id' => currentFacility()->id,
            'user_id' => $admin->id,
            'shift' => 'afternoon',
            'cash_drawer' => 'Main Counter',
            'status' => 'open',
        ]);
        $this->assertDatabaseHas('activity_logs', ['event' => 'cashier_session_opened']);
        $this->assertStringStartsWith('CSH-'.now()->format('Y').'-', CashierSession::query()->firstOrFail()->session_number);
    }

    public function test_cashier_cannot_open_duplicate_active_session(): void
    {
        $admin = $this->bootstrappedFacility();
        $this->actingAs($admin);

        app(CashierSessionService::class)->openSession($admin, 'morning', '0', 'Main Counter');

        Livewire::actingAs($admin)
            ->test(CashierSessions::class)
            ->call('create')
            ->set('openForm.shift', 'afternoon')
            ->call('openSession')
            ->assertHasErrors(['session']);

        $this->assertSame(1, CashierSession::query()->where('user_id', $admin->id)->count());
        $this->assertDatabaseHas('activity_logs', ['event' => 'cashier_session_open_attempt_blocked']);
    }

    public function test_receive_payment_opens_directly_without_cashier_session_even_when_legacy_setting_is_on(): void
    {
        $admin = $this->bootstrappedFacility();
        $invoice = $this->createCashInvoice($admin, 'INV-BIL-NO-SESSION', 10000);

        $this->setBillingSetting('billing_require_cashier_session', 'true');

        Livewire::actingAs($admin)
            ->test(InvoiceShow::class, ['invoice' => $invoice])
            ->call('openPaymentModal')
            ->assertSet('showOpenSessionPrompt', false)
            ->assertSet('showPaymentModal', true);
    }

    public function test_payment_confirmation_without_open_session_saves_receiver_and_null_session(): void
    {
        $admin = $this->bootstrappedFacility();
        $this->actingAs($admin);
        $invoice = $this->createCashInvoice($admin, 'INV-BIL-SESSION-NULL', 10000);
        $cash = PaymentMethod::query()->where('code', 'CASH')->firstOrFail();

        $this->setBillingSetting('billing_require_cashier_session', 'true');

        Livewire::actingAs($admin)
            ->test(InvoiceShow::class, ['invoice' => $invoice])
            ->call('openPaymentModal')
            ->set('payment_method_id', $cash->id)
            ->set('amount', '10000')
            ->call('confirmPayment')
            ->assertHasNoErrors()
            ->assertSet('showPaymentModal', false);

        $this->assertDatabaseHas('payments', [
            'invoice_id' => $invoice->id,
            'cashier_session_id' => null,
            'received_by' => $admin->id,
            'confirmed_by' => $admin->id,
            'amount' => 10000,
        ]);
        $this->assertDatabaseHas('receipts', ['invoice_id' => $invoice->id, 'cashier_name_snapshot' => $admin->name]);
        $this->assertDatabaseHas('activity_logs', ['event' => 'payment_confirmed', 'subject_type' => Payment::class]);

        $payment = Payment::query()->where('invoice_id', $invoice->id)->firstOrFail();
        $this->get(route('billing.receipts.print', $payment->receipt))->assertOk()->assertSee('Received By')->assertSee($admin->name);
    }

    public function test_billing_cashier_report_groups_payments_by_receiver(): void
    {
        $admin = $this->bootstrappedFacility();
        $this->actingAs($admin);
        $cash = PaymentMethod::query()->where('code', 'CASH')->firstOrFail();

        app(PaymentConfirmationService::class)->confirmPayment($this->createCashInvoice($admin, 'INV-BIL-CASHIER-RPT-1', 3000), $cash, 3000, $admin);
        app(PaymentConfirmationService::class)->confirmPayment($this->createCashInvoice($admin, 'INV-BIL-CASHIER-RPT-2', 2000), $cash, 2000, $admin);

        $this->get(route('reports.billing.cashiers'))
            ->assertOk()
            ->assertSee('Payments by Cashier')
            ->assertSee($admin->name)
            ->assertSee('5,000.00');
    }

    public function test_payment_confirmation_attaches_active_cashier_session_when_one_exists(): void
    {
        $admin = $this->bootstrappedFacility();
        $this->actingAs($admin);

        $session = app(CashierSessionService::class)->openSession($admin, 'morning', '0', 'Main Counter');
        $invoice = $this->createCashInvoice($admin, 'INV-BIL-SESSION-PAY', 10000);
        $cash = PaymentMethod::query()->where('code', 'CASH')->firstOrFail();

        Livewire::actingAs($admin)
            ->test(InvoiceShow::class, ['invoice' => $invoice])
            ->call('openPaymentModal')
            ->set('payment_method_id', $cash->id)
            ->set('amount', '10000')
            ->call('confirmPayment')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('payments', [
            'invoice_id' => $invoice->id,
            'cashier_session_id' => $session->id,
            'amount' => 10000,
        ]);
    }

    public function test_unauthorized_user_cannot_confirm_payment(): void
    {
        $admin = $this->bootstrappedFacility();
        $invoice = $this->createCashInvoice($admin, 'INV-BIL-UNAUTH', 10000);
        $cash = PaymentMethod::query()->where('code', 'CASH')->firstOrFail();
        $user = User::factory()->create();
        $user->givePermissionTo('invoices.view');

        Livewire::actingAs($user)
            ->test(InvoiceShow::class, ['invoice' => $invoice])
            ->set('payment_method_id', $cash->id)
            ->set('amount', '10000')
            ->call('confirmPayment')
            ->assertForbidden();
    }

    public function test_duplicate_payment_submission_does_not_create_duplicate_payment(): void
    {
        $admin = $this->bootstrappedFacility();
        $this->actingAs($admin);
        $invoice = $this->createCashInvoice($admin, 'INV-BIL-DUPLICATE', 10000);
        $cash = PaymentMethod::query()->where('code', 'CASH')->firstOrFail();

        app(PaymentConfirmationService::class)->confirmPayment($invoice, $cash, 10000, $admin);

        try {
            app(PaymentConfirmationService::class)->confirmPayment($invoice->refresh(), $cash, 10000, $admin);
            $this->fail('Duplicate payment was not blocked.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('amount', $exception->errors());
        }

        $this->assertSame(1, Payment::query()->where('invoice_id', $invoice->id)->count());
    }

    public function test_facility_scoping_hides_other_facility_payment_methods(): void
    {
        $admin = $this->bootstrappedFacility();
        $other = Facility::query()->create(['name' => 'Other Facility', 'code' => 'OTH', 'facility_type' => FacilityType::Dispensary, 'ownership_type' => OwnershipType::Private, 'phone_primary' => '+255700000111', 'region' => 'Dar es Salaam', 'district' => 'Ilala', 'ward' => 'Upanga', 'physical_address' => 'Upanga', 'setup_completed_at' => now(), 'created_by' => $admin->id, 'updated_by' => $admin->id]);
        PaymentMethod::query()->create(['facility_id' => $other->id, 'name' => 'Other Wallet', 'code' => 'OTHER_WALLET', 'type' => 'mobile_money', 'is_active' => true]);

        $this->assertFalse(PaymentMethod::query()->forCurrentFacility()->where('code', 'OTHER_WALLET')->exists());
    }

    private function bootstrappedFacility(): User
    {
        $admin = User::factory()->superAdmin()->create(['email' => fake()->unique()->safeEmail()]);
        Facility::query()->create(['name' => 'Vikindu Dispensary', 'code' => 'VDP', 'facility_type' => FacilityType::Dispensary, 'ownership_type' => OwnershipType::Private, 'phone_primary' => '+255700000000', 'region' => 'Dar es Salaam', 'district' => 'Temeke', 'ward' => 'Vikindu', 'physical_address' => 'Vikindu', 'setup_completed_at' => now(), 'created_by' => $admin->id, 'updated_by' => $admin->id]);
        $this->seed([PermissionSeeder::class, DepartmentSeeder::class, PaymentMethodSeeder::class, BillingSettingsSeeder::class]);
        foreach (Permission::query()->pluck('name') as $permission) {
            $admin->givePermissionTo($permission);
        }

        return $admin;
    }

    private function createCashInvoice(User $admin, string $number, int $amount): Invoice
    {
        $patient = Patient::factory()->create(['facility_id' => currentFacility()->id, 'created_by' => $admin->id]);
        $invoice = Invoice::query()->create([
            'facility_id' => currentFacility()->id,
            'patient_id' => $patient->id,
            'invoice_number' => $number,
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

    private function createCashInvoiceForVisit(User $admin, string $number, int $amount, Department $destination, string $visitStatus = 'awaiting_payment', string $payerType = 'cash'): Invoice
    {
        $patient = Patient::factory()->create(['facility_id' => currentFacility()->id, 'created_by' => $admin->id]);
        $billing = Department::query()->forCurrentFacility()->where('code', 'BIL')->firstOrFail();
        $visit = Visit::query()->create([
            'facility_id' => currentFacility()->id,
            'patient_id' => $patient->id,
            'visit_number' => 'VIS-BIL-'.fake()->unique()->numerify('######'),
            'visit_type' => 'new_patient',
            'payer_type' => $payerType,
            'destination_department_id' => $destination->id,
            'current_department_id' => $billing->id,
            'visit_status' => $visitStatus,
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
            'queue_status' => 'waiting',
            'priority' => 'normal',
            'position' => 1,
            'checked_in_at' => now(),
            'created_by' => $admin->id,
        ]);

        $invoice = Invoice::query()->create([
            'facility_id' => currentFacility()->id,
            'patient_id' => $patient->id,
            'visit_id' => $visit->id,
            'invoice_number' => $number,
            'payer_type' => $payerType,
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

    private function setBillingSetting(string $key, string $value): void
    {
        FacilitySetting::query()->updateOrCreate(
            ['facility_id' => currentFacility()->id, 'key' => $key],
            ['value' => $value, 'type' => 'boolean', 'group' => 'billing', 'is_public' => false],
        );
    }
}
