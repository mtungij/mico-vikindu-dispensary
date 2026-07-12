<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            if (! Schema::hasColumn('invoices', 'invoice_type')) $table->string('invoice_type')->default('patient_visit')->after('invoice_number');
            if (! Schema::hasColumn('invoices', 'insurance_provider_id')) $table->foreignId('insurance_provider_id')->nullable()->after('patient_payer_profile_id')->constrained()->nullOnDelete();
            if (! Schema::hasColumn('invoices', 'corporate_account_id')) $table->foreignId('corporate_account_id')->nullable()->after('insurance_provider_id')->constrained()->nullOnDelete();
            if (! Schema::hasColumn('invoices', 'waiver_amount')) $table->decimal('waiver_amount', 15, 2)->default(0)->after('discount_amount');
            if (! Schema::hasColumn('invoices', 'gross_total')) $table->decimal('gross_total', 15, 2)->default(0)->after('tax_amount');
            if (! Schema::hasColumn('invoices', 'patient_amount')) $table->decimal('patient_amount', 15, 2)->default(0)->after('gross_total');
            if (! Schema::hasColumn('invoices', 'insurance_amount')) $table->decimal('insurance_amount', 15, 2)->default(0)->after('patient_amount');
            if (! Schema::hasColumn('invoices', 'corporate_amount')) $table->decimal('corporate_amount', 15, 2)->default(0)->after('insurance_amount');
            if (! Schema::hasColumn('invoices', 'refunded_amount')) $table->decimal('refunded_amount', 15, 2)->default(0)->after('paid_amount');
            if (! Schema::hasColumn('invoices', 'status')) $table->string('status')->default('open')->index()->after('balance_amount');
            if (! Schema::hasColumn('invoices', 'payment_status')) $table->string('payment_status')->default('unpaid')->index()->after('status');
            if (! Schema::hasColumn('invoices', 'finalized_at')) $table->timestamp('finalized_at')->nullable()->after('due_at');
            if (! Schema::hasColumn('invoices', 'finalized_by')) $table->foreignId('finalized_by')->nullable()->after('finalized_at')->constrained('users')->nullOnDelete();
            if (! Schema::hasColumn('invoices', 'voided_at')) $table->timestamp('voided_at')->nullable()->after('finalized_by');
            if (! Schema::hasColumn('invoices', 'voided_by')) $table->foreignId('voided_by')->nullable()->after('voided_at')->constrained('users')->nullOnDelete();
            if (! Schema::hasColumn('invoices', 'void_reason')) $table->text('void_reason')->nullable()->after('voided_by');
        });

        Schema::table('invoice_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('invoice_items', 'facility_id')) $table->foreignId('facility_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            if (! Schema::hasColumn('invoice_items', 'patient_id')) $table->foreignId('patient_id')->nullable()->after('invoice_id')->constrained()->nullOnDelete();
            if (! Schema::hasColumn('invoice_items', 'visit_id')) $table->foreignId('visit_id')->nullable()->after('patient_id')->constrained()->nullOnDelete();
            if (! Schema::hasColumn('invoice_items', 'reference_type')) $table->string('reference_type')->nullable()->after('item_type');
            if (! Schema::hasColumn('invoice_items', 'reference_id')) $table->unsignedBigInteger('reference_id')->nullable()->after('reference_type');
            if (! Schema::hasColumn('invoice_items', 'code_snapshot')) $table->string('code_snapshot')->nullable()->after('reference_id');
            if (! Schema::hasColumn('invoice_items', 'description_snapshot')) $table->string('description_snapshot')->nullable()->after('description');
            if (! Schema::hasColumn('invoice_items', 'department_id')) $table->foreignId('department_id')->nullable()->after('description_snapshot')->constrained()->nullOnDelete();
            if (! Schema::hasColumn('invoice_items', 'gross_amount')) $table->decimal('gross_amount', 15, 2)->default(0)->after('unit_price');
            if (! Schema::hasColumn('invoice_items', 'waiver_amount')) $table->decimal('waiver_amount', 15, 2)->default(0)->after('discount_amount');
            if (! Schema::hasColumn('invoice_items', 'corporate_amount')) $table->decimal('corporate_amount', 15, 2)->default(0)->after('insurance_amount');
            if (! Schema::hasColumn('invoice_items', 'net_amount')) $table->decimal('net_amount', 15, 2)->default(0)->after('patient_amount');
            if (! Schema::hasColumn('invoice_items', 'paid_amount')) $table->decimal('paid_amount', 15, 2)->default(0)->after('net_amount');
            if (! Schema::hasColumn('invoice_items', 'service_date')) $table->date('service_date')->nullable()->after('status');
            if (! Schema::hasColumn('invoice_items', 'performed_at')) $table->timestamp('performed_at')->nullable()->after('service_date');
            if (! Schema::hasColumn('invoice_items', 'cancelled_at')) $table->timestamp('cancelled_at')->nullable()->after('performed_at');
            if (! Schema::hasColumn('invoice_items', 'cancelled_by')) $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
            if (! Schema::hasColumn('invoice_items', 'cancellation_reason')) $table->text('cancellation_reason')->nullable()->after('cancelled_by');
            if (! Schema::hasColumn('invoice_items', 'price_snapshot')) $table->json('price_snapshot')->nullable()->after('cancellation_reason');
            if (! Schema::hasColumn('invoice_items', 'coverage_snapshot')) $table->json('coverage_snapshot')->nullable()->after('price_snapshot');
            if (! Schema::hasColumn('invoice_items', 'updated_by')) $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->index(['facility_id', 'status'], 'inv_item_fac_status_idx');
            $table->index(['reference_type', 'reference_id'], 'inv_item_reference_idx');
        });

        foreach (['payment', 'receipt', 'cashier_session', 'payment_reversal', 'payment_refund', 'patient_deposit'] as $name) {
            Schema::create($name.'_number_sequences', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
                $table->unsignedSmallInteger('year')->nullable();
                $table->unsignedBigInteger('last_number')->default(0);
                $table->timestamps();
                $table->unique(['facility_id', 'year']);
            });
        }

        Schema::create('payment_methods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 50);
            $table->string('type')->index();
            $table->boolean('requires_reference')->default(false);
            $table->boolean('requires_phone')->default(false);
            $table->boolean('requires_bank')->default(false);
            $table->boolean('is_cash')->default(false);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'code'], 'pay_method_fac_code_uq');
        });

        Schema::create('cashier_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('session_number', 50);
            $table->timestamp('opened_at');
            $table->decimal('opening_float', 15, 2)->default(0);
            $table->string('status')->default('open')->index();
            $table->timestamp('closed_at')->nullable();
            $table->decimal('expected_cash', 15, 2)->nullable();
            $table->decimal('declared_cash', 15, 2)->nullable();
            $table->decimal('variance', 15, 2)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'session_number'], 'cash_sess_fac_no_uq');
            $table->index(['facility_id', 'user_id', 'status'], 'cash_sess_fac_user_status_idx');
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('cashier_session_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payment_number', 50);
            $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('currency', 10)->default('TZS');
            $table->string('transaction_reference')->nullable();
            $table->string('payer_name')->nullable();
            $table->string('payer_phone')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('card_last_four', 4)->nullable();
            $table->timestamp('payment_date');
            $table->string('status')->default('confirmed')->index();
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reversal_reason')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'payment_number'], 'payment_fac_no_uq');
            $table->index(['facility_id', 'payment_date'], 'payment_fac_date_idx');
        });

        Schema::create('payment_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_item_id')->nullable()->constrained()->cascadeOnDelete();
            $table->decimal('allocated_amount', 15, 2);
            $table->string('allocation_type')->default('invoice');
            $table->foreignId('allocated_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('allocated_at');
            $table->timestamp('reversed_at')->nullable();
            $table->text('reversal_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->string('receipt_number', 50);
            $table->timestamp('receipt_date');
            $table->decimal('amount', 15, 2);
            $table->string('payment_method_snapshot');
            $table->string('transaction_reference_snapshot')->nullable();
            $table->string('cashier_name_snapshot');
            $table->string('status')->default('issued')->index();
            $table->foreignId('original_receipt_id')->nullable()->constrained('receipts')->nullOnDelete();
            $table->unsignedInteger('reprint_count')->default(0);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'receipt_number'], 'receipt_fac_no_uq');
        });

        Schema::create('visit_payment_handoffs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('destination_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('destination_type')->nullable();
            $table->string('destination_reference_type')->nullable();
            $table->unsignedBigInteger('destination_reference_id')->nullable();
            $table->text('reason')->nullable();
            $table->decimal('required_patient_amount', 15, 2)->default(0);
            $table->string('status')->default('pending_payment')->index();
            $table->string('priority')->default('normal');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['facility_id', 'visit_id', 'status'], 'handoff_fac_visit_status_idx');
        });

        Schema::create('cashier_handovers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_cashier_session_id')->constrained('cashier_sessions')->cascadeOnDelete();
            $table->foreignId('to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('handed_over_cash', 15, 2)->default(0);
            $table->text('handed_over_documents')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('handed_over_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('handed_over_at');
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        Schema::create('billing_discounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_item_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('discount_type');
            $table->decimal('discount_value', 15, 2);
            $table->decimal('discount_amount', 15, 2);
            $table->text('reason');
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('billing_waivers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_item_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('waiver_type');
            $table->decimal('amount', 15, 2);
            $table->text('reason');
            $table->string('supporting_document_path')->nullable();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('invoice_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_item_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('adjustment_type');
            $table->decimal('amount', 15, 2);
            $table->text('reason');
            $table->string('status')->default('pending');
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('payment_reversals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->string('reversal_number', 50);
            $table->decimal('amount', 15, 2);
            $table->text('reason');
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'reversal_number'], 'pay_rev_fac_no_uq');
        });

        Schema::create('payment_refunds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('refund_number', 50);
            $table->decimal('amount', 15, 2);
            $table->foreignId('refund_method_id')->constrained('payment_methods')->restrictOnDelete();
            $table->text('reason');
            $table->string('transaction_reference')->nullable();
            $table->string('status')->default('pending');
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('cashier_session_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'refund_number'], 'pay_refund_fac_no_uq');
        });

        Schema::create('patient_deposits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->string('deposit_number', 50);
            $table->decimal('amount', 15, 2);
            $table->decimal('available_amount', 15, 2);
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('available');
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('received_at');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'deposit_number'], 'patient_dep_fac_no_uq');
        });

        Schema::create('patient_deposit_applications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('patient_deposit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->foreignId('applied_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('applied_at');
            $table->timestamp('reversed_at')->nullable();
            $table->text('reversal_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('patient_credit_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->string('transaction_type');
            $table->string('reference_type');
            $table->unsignedBigInteger('reference_id');
            $table->decimal('amount', 15, 2);
            $table->decimal('balance_after', 15, 2);
            $table->text('reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');
        });

        Schema::create('patient_credit_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->decimal('credit_limit', 15, 2)->default(0);
            $table->decimal('current_balance', 15, 2)->default(0);
            $table->unsignedInteger('payment_terms_days')->nullable();
            $table->string('status')->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'patient_id'], 'patient_credit_fac_patient_uq');
        });

        Schema::create('billing_payment_overrides', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('handoff_id')->nullable()->constrained('visit_payment_handoffs')->nullOnDelete();
            $table->string('override_type');
            $table->decimal('amount_outstanding', 15, 2);
            $table->text('reason');
            $table->foreignId('authorized_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('used_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('status')->default('approved');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['billing_payment_overrides','patient_credit_profiles','patient_credit_transactions','patient_deposit_applications','patient_deposits','payment_refunds','payment_reversals','invoice_adjustments','billing_waivers','billing_discounts','cashier_handovers','visit_payment_handoffs','receipts','payment_allocations','payments','cashier_sessions','payment_methods'] as $table) {
            Schema::dropIfExists($table);
        }
        foreach (['patient_deposit', 'payment_refund', 'payment_reversal', 'cashier_session', 'receipt', 'payment'] as $name) {
            Schema::dropIfExists($name.'_number_sequences');
        }
    }
};
