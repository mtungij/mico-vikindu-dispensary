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
use App\Models\FacilitySetting;
use App\Models\Facility;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Patient;
use App\Models\PaymentMethod;
use App\Models\Permission;
use App\Models\User;
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
            ->assertSee('payment method id inahitajika.')
            ->assertSee('amount inahitajika.');
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

    public function test_receive_payment_opens_directly_when_cashier_session_setting_is_off(): void
    {
        $admin = $this->bootstrappedFacility();
        $invoice = $this->createCashInvoice($admin, 'INV-BIL-SESSION-OFF', 10000);

        $this->setBillingSetting('billing_require_cashier_session', 'false');

        Livewire::actingAs($admin)
            ->test(InvoiceShow::class, ['invoice' => $invoice])
            ->call('openPaymentModal')
            ->assertSet('showOpenSessionPrompt', false)
            ->assertSet('showPaymentModal', true);
    }

    public function test_receive_payment_prompts_for_session_and_auto_returns_after_opening(): void
    {
        $admin = $this->bootstrappedFacility();
        $invoice = $this->createCashInvoice($admin, 'INV-BIL-SESSION-ON', 10000);

        $this->setBillingSetting('billing_require_cashier_session', 'true');

        Livewire::actingAs($admin)
            ->test(InvoiceShow::class, ['invoice' => $invoice])
            ->call('openPaymentModal')
            ->assertSet('showPaymentModal', false)
            ->assertSet('showOpenSessionPrompt', true)
            ->call('openCashierSessionFromPaymentPrompt')
            ->assertSet('showCashierSessionModal', true)
            ->set('cashierSessionForm.shift', 'morning')
            ->set('cashierSessionForm.opening_float', '0')
            ->call('openCashierSession')
            ->assertHasNoErrors()
            ->assertSet('showCashierSessionModal', false)
            ->assertSet('showPaymentModal', true);

        $this->assertDatabaseHas('cashier_sessions', ['user_id' => $admin->id, 'status' => 'open']);
        $this->assertDatabaseHas('activity_logs', ['event' => 'cashier_session_prompt_shown']);
    }

    public function test_payment_confirmation_attaches_active_cashier_session_when_required(): void
    {
        $admin = $this->bootstrappedFacility();
        $this->actingAs($admin);
        $this->setBillingSetting('billing_require_cashier_session', 'true');

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

    public function test_facility_scoping_hides_other_facility_payment_methods(): void
    {
        $admin = $this->bootstrappedFacility();
        $other = Facility::query()->create(['name'=>'Other Facility','code'=>'OTH','facility_type'=>FacilityType::Dispensary,'ownership_type'=>OwnershipType::Private,'phone_primary'=>'+255700000111','region'=>'Dar es Salaam','district'=>'Ilala','ward'=>'Upanga','physical_address'=>'Upanga','setup_completed_at'=>now(),'created_by'=>$admin->id,'updated_by'=>$admin->id]);
        PaymentMethod::query()->create(['facility_id'=>$other->id,'name'=>'Other Wallet','code'=>'OTHER_WALLET','type'=>'mobile_money','is_active'=>true]);

        $this->assertFalse(PaymentMethod::query()->forCurrentFacility()->where('code', 'OTHER_WALLET')->exists());
    }

    private function bootstrappedFacility(): User
    {
        $admin = User::factory()->superAdmin()->create(['email' => fake()->unique()->safeEmail()]);
        Facility::query()->create(['name'=>'Vikindu Dispensary','code'=>'VDP','facility_type'=>FacilityType::Dispensary,'ownership_type'=>OwnershipType::Private,'phone_primary'=>'+255700000000','region'=>'Dar es Salaam','district'=>'Temeke','ward'=>'Vikindu','physical_address'=>'Vikindu','setup_completed_at'=>now(),'created_by'=>$admin->id,'updated_by'=>$admin->id]);
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

    private function setBillingSetting(string $key, string $value): void
    {
        FacilitySetting::query()->updateOrCreate(
            ['facility_id' => currentFacility()->id, 'key' => $key],
            ['value' => $value, 'type' => 'boolean', 'group' => 'billing', 'is_public' => false],
        );
    }
}
