<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['dispensing_number_sequences', 'purchase_order_number_sequences', 'purchase_receipt_number_sequences', 'stock_transfer_number_sequences', 'stock_adjustment_number_sequences', 'stock_count_number_sequences', 'pharmacy_return_number_sequences', 'supplier_return_number_sequences'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table): void {
                $table->id();
                $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
                $table->unsignedSmallInteger('year')->nullable();
                $table->unsignedBigInteger('last_number')->default(0);
                $table->timestamps();
                $table->unique(['facility_id', 'year']);
            });
        }

        Schema::create('medicine_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('code', 40);
            $table->text('description')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('medicine_categories')->nullOnDelete();
            $table->string('icon')->nullable();
            $table->string('color', 20)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'code']);
            $table->unique(['facility_id', 'name']);
        });

        Schema::create('generic_medicines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('code', 60);
            $table->text('description')->nullable();
            $table->string('therapeutic_class')->nullable();
            $table->string('pharmacological_class')->nullable();
            $table->boolean('controlled_drug')->default(false);
            $table->boolean('prescription_required')->default(true);
            $table->string('pregnancy_warning')->nullable();
            $table->text('common_indications')->nullable();
            $table->text('common_contraindications')->nullable();
            $table->text('common_side_effects')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'code']);
            $table->unique(['facility_id', 'name']);
        });

        Schema::create('dosage_forms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('code', 40);
            $table->text('description')->nullable();
            $table->boolean('is_liquid')->default(false);
            $table->boolean('is_injectable')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'code']);
            $table->unique(['facility_id', 'name']);
        });

        Schema::create('medicine_units', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('symbol', 40);
            $table->text('description')->nullable();
            $table->boolean('decimal_allowed')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'symbol']);
            $table->unique(['facility_id', 'name']);
        });

        Schema::create('medicine_routes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('code', 40);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'code']);
            $table->unique(['facility_id', 'name']);
        });

        Schema::create('medicines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('medicine_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('generic_medicine_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('dosage_form_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('default_route_id')->nullable()->constrained('medicine_routes')->nullOnDelete();
            $table->foreignId('purchase_unit_id')->constrained('medicine_units')->restrictOnDelete();
            $table->foreignId('dispensing_unit_id')->constrained('medicine_units')->restrictOnDelete();
            $table->foreignId('service_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('brand_name')->nullable();
            $table->string('code', 60);
            $table->string('barcode')->nullable();
            $table->string('strength')->nullable();
            $table->decimal('pack_size', 12, 3)->default(1);
            $table->decimal('purchase_to_dispensing_factor', 12, 3)->default(1);
            $table->string('manufacturer')->nullable();
            $table->string('country_of_origin')->nullable();
            $table->text('description')->nullable();
            $table->text('storage_instructions')->nullable();
            $table->decimal('reorder_level', 15, 3)->default(0);
            $table->decimal('maximum_stock_level', 15, 3)->nullable();
            $table->decimal('default_dispensing_price', 15, 2)->nullable();
            $table->boolean('prescription_required')->default(true);
            $table->boolean('controlled_drug')->default(false);
            $table->boolean('allow_substitution')->default(true);
            $table->boolean('track_batch')->default(true);
            $table->boolean('track_expiry')->default(true);
            $table->boolean('taxable')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'code']);
            $table->unique(['facility_id', 'barcode']);
            $table->index(['facility_id', 'medicine_category_id', 'is_active']);
        });

        Schema::create('medicine_packagings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('medicine_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->foreignId('purchase_unit_id')->constrained('medicine_units')->restrictOnDelete();
            $table->foreignId('dispensing_unit_id')->constrained('medicine_units')->restrictOnDelete();
            $table->decimal('conversion_factor', 15, 3);
            $table->string('barcode')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'barcode']);
            $table->index(['facility_id', 'medicine_id', 'is_default']);
        });

        Schema::create('suppliers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('code', 60);
            $table->string('supplier_type');
            $table->string('contact_person')->nullable();
            $table->string('phone_primary');
            $table->string('phone_secondary')->nullable();
            $table->string('email')->nullable();
            $table->text('physical_address')->nullable();
            $table->string('postal_address')->nullable();
            $table->string('region')->nullable();
            $table->string('district')->nullable();
            $table->string('tin_number')->nullable();
            $table->string('vrn_number')->nullable();
            $table->unsignedInteger('payment_terms_days')->nullable();
            $table->decimal('credit_limit', 15, 2)->nullable();
            $table->text('bank_details')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'code']);
        });

        Schema::create('stock_locations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('code', 60);
            $table->string('location_type');
            $table->text('description')->nullable();
            $table->boolean('is_dispensing_location')->default(false);
            $table->boolean('is_receiving_location')->default(false);
            $table->boolean('allows_transfers')->default(true);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'code']);
        });

        Schema::create('purchase_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->string('purchase_order_number', 60);
            $table->date('order_date');
            $table->date('expected_delivery_date')->nullable();
            $table->string('status')->index();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'purchase_order_number']);
        });

        Schema::create('purchase_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $table->foreignId('packaging_id')->nullable()->constrained('medicine_packagings')->nullOnDelete();
            $table->decimal('ordered_quantity', 15, 3);
            $table->decimal('received_quantity', 15, 3)->default(0);
            $table->decimal('unit_cost', 15, 4);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('purchase_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('receipt_number', 60);
            $table->string('supplier_invoice_number')->nullable();
            $table->string('supplier_delivery_note')->nullable();
            $table->dateTime('received_at');
            $table->foreignId('stock_location_id')->constrained()->restrictOnDelete();
            $table->string('status')->index();
            $table->text('notes')->nullable();
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('verified_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'receipt_number']);
        });

        Schema::create('purchase_receipt_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $table->foreignId('packaging_id')->nullable()->constrained('medicine_packagings')->nullOnDelete();
            $table->string('batch_number');
            $table->date('manufacturing_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->decimal('quantity_received', 15, 3);
            $table->decimal('bonus_quantity', 15, 3)->default(0);
            $table->decimal('rejected_quantity', 15, 3)->default(0);
            $table->decimal('unit_cost', 15, 4);
            $table->decimal('selling_price', 15, 2)->nullable();
            $table->decimal('total_cost', 15, 2);
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('medicine_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_location_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('purchase_receipt_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('batch_number', 80);
            $table->date('manufacturing_date')->nullable();
            $table->date('expiry_date')->nullable()->index();
            $table->decimal('received_quantity', 15, 3);
            $table->decimal('available_quantity', 15, 3)->default(0)->index();
            $table->decimal('reserved_quantity', 15, 3)->default(0);
            $table->decimal('damaged_quantity', 15, 3)->default(0);
            $table->decimal('unit_cost', 15, 4);
            $table->decimal('selling_price_snapshot', 15, 2)->nullable();
            $table->string('status')->index();
            $table->dateTime('received_at');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'medicine_id', 'stock_location_id', 'batch_number'], 'med_batch_fac_med_loc_no_unq');
            $table->index(['facility_id', 'medicine_id', 'stock_location_id']);
        });

        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $table->foreignId('medicine_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('stock_location_id')->constrained()->restrictOnDelete();
            $table->string('movement_type')->index();
            $table->string('direction')->index();
            $table->decimal('quantity', 15, 3);
            $table->decimal('unit_cost', 15, 4)->nullable();
            $table->decimal('balance_before', 15, 3);
            $table->decimal('balance_after', 15, 3);
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('performed_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('occurred_at')->index();
            $table->timestamp('created_at')->nullable();
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('stock_transfers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->string('transfer_number', 60);
            $table->foreignId('from_location_id')->constrained('stock_locations')->restrictOnDelete();
            $table->foreignId('to_location_id')->constrained('stock_locations')->restrictOnDelete();
            $table->string('status')->index();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('requested_at');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->foreignId('dispatched_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('dispatched_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('received_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'transfer_number']);
        });

        Schema::create('stock_transfer_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $table->foreignId('medicine_batch_id')->constrained()->restrictOnDelete();
            $table->decimal('requested_quantity', 15, 3);
            $table->decimal('dispatched_quantity', 15, 3)->default(0);
            $table->decimal('received_quantity', 15, 3)->default(0);
            $table->decimal('rejected_quantity', 15, 3)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->string('adjustment_number', 60);
            $table->foreignId('stock_location_id')->constrained()->restrictOnDelete();
            $table->string('adjustment_type');
            $table->string('reason');
            $table->string('status')->index();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'adjustment_number']);
        });

        Schema::create('stock_adjustment_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_adjustment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $table->foreignId('medicine_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('system_quantity', 15, 3);
            $table->decimal('adjusted_quantity', 15, 3);
            $table->decimal('difference_quantity', 15, 3);
            $table->decimal('unit_cost', 15, 4)->nullable();
            $table->string('reason')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_counts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_location_id')->constrained()->restrictOnDelete();
            $table->string('count_number', 60);
            $table->date('count_date');
            $table->string('status')->index();
            $table->foreignId('counted_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('posted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'count_number']);
        });

        Schema::create('stock_count_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_count_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $table->foreignId('medicine_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('system_quantity', 15, 3);
            $table->decimal('counted_quantity', 15, 3)->nullable();
            $table->decimal('variance_quantity', 15, 3)->nullable();
            $table->decimal('unit_cost', 15, 4);
            $table->decimal('variance_value', 15, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('prescription_items', function (Blueprint $table): void {
            $table->foreignId('medicine_id')->nullable()->after('prescription_id')->constrained()->nullOnDelete();
            $table->decimal('dispensed_quantity', 12, 3)->default(0)->after('quantity');
            $table->decimal('remaining_quantity', 12, 3)->nullable()->after('dispensed_quantity');
            $table->foreignId('substitution_medicine_id')->nullable()->after('remaining_quantity')->constrained('medicines')->nullOnDelete();
            $table->text('substitution_reason')->nullable()->after('substitution_medicine_id');
            $table->string('dispensing_status')->nullable()->after('substitution_reason')->index();
            $table->decimal('unit_price_snapshot', 15, 2)->nullable()->after('dispensing_status');
            $table->decimal('patient_amount', 15, 2)->nullable()->after('unit_price_snapshot');
            $table->decimal('insurance_amount', 15, 2)->nullable()->after('patient_amount');
            $table->decimal('payer_amount', 15, 2)->nullable()->after('insurance_amount');
        });

        Schema::create('dispensings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('prescription_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->constrained()->restrictOnDelete();
            $table->string('dispensing_number', 60);
            $table->foreignId('stock_location_id')->constrained()->restrictOnDelete();
            $table->string('status')->index();
            $table->string('payment_status')->index();
            $table->foreignId('dispensed_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('dispensed_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'dispensing_number']);
        });

        Schema::create('dispensing_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dispensing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prescription_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $table->foreignId('medicine_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('prescribed_quantity', 12, 3);
            $table->decimal('dispensed_quantity', 12, 3);
            $table->decimal('unit_price_snapshot', 15, 2);
            $table->decimal('total_amount', 15, 2);
            $table->decimal('patient_amount', 15, 2);
            $table->decimal('insurance_amount', 15, 2)->default(0);
            $table->decimal('payer_amount', 15, 2);
            $table->foreignId('substitution_from_medicine_id')->nullable()->constrained('medicines')->nullOnDelete();
            $table->text('substitution_reason')->nullable();
            $table->text('instructions_snapshot')->nullable();
            $table->string('status')->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('dispensing_batch_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dispensing_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medicine_batch_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_cost_snapshot', 15, 4);
            $table->date('expiry_date_snapshot')->nullable();
            $table->timestamps();
        });

        Schema::create('pharmacy_returns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('dispensing_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->string('return_number', 60);
            $table->string('status')->index();
            $table->string('reason');
            $table->foreignId('returned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('returned_at');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'return_number']);
        });

        Schema::create('pharmacy_return_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pharmacy_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dispensing_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $table->foreignId('medicine_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity_returned', 12, 3);
            $table->string('condition_status');
            $table->boolean('restock_allowed')->default(false);
            $table->decimal('refund_amount', 15, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('supplier_returns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_location_id')->constrained()->restrictOnDelete();
            $table->string('return_number', 60);
            $table->string('status')->index();
            $table->string('reason');
            $table->dateTime('returned_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'return_number']);
        });

        Schema::create('supplier_return_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $table->foreignId('medicine_batch_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_cost', 15, 4);
            $table->decimal('total_cost', 15, 2);
            $table->string('reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_return_items');
        Schema::dropIfExists('supplier_returns');
        Schema::dropIfExists('pharmacy_return_items');
        Schema::dropIfExists('pharmacy_returns');
        Schema::dropIfExists('dispensing_batch_allocations');
        Schema::dropIfExists('dispensing_items');
        Schema::dropIfExists('dispensings');
        Schema::table('prescription_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('medicine_id');
            $table->dropConstrainedForeignId('substitution_medicine_id');
            $table->dropColumn(['dispensed_quantity', 'remaining_quantity', 'substitution_reason', 'dispensing_status', 'unit_price_snapshot', 'patient_amount', 'insurance_amount', 'payer_amount']);
        });
        Schema::dropIfExists('stock_count_items');
        Schema::dropIfExists('stock_counts');
        Schema::dropIfExists('stock_adjustment_items');
        Schema::dropIfExists('stock_adjustments');
        Schema::dropIfExists('stock_transfer_items');
        Schema::dropIfExists('stock_transfers');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('medicine_batches');
        Schema::dropIfExists('purchase_receipt_items');
        Schema::dropIfExists('purchase_receipts');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('stock_locations');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('medicine_packagings');
        Schema::dropIfExists('medicines');
        Schema::dropIfExists('medicine_routes');
        Schema::dropIfExists('medicine_units');
        Schema::dropIfExists('dosage_forms');
        Schema::dropIfExists('generic_medicines');
        Schema::dropIfExists('medicine_categories');

        foreach (['supplier_return_number_sequences', 'pharmacy_return_number_sequences', 'stock_count_number_sequences', 'stock_adjustment_number_sequences', 'stock_transfer_number_sequences', 'purchase_receipt_number_sequences', 'purchase_order_number_sequences', 'dispensing_number_sequences'] as $tableName) {
            Schema::dropIfExists($tableName);
        }
    }
};
