<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->string('code', 30);
            $table->text('description')->nullable();
            $table->string('category_type')->index();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
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

        Schema::create('services', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 120);
            $table->string('code', 40);
            $table->text('description')->nullable();
            $table->string('service_type')->index();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->boolean('requires_clinical_order')->default(false);
            $table->boolean('requires_payment')->default(true);
            $table->boolean('requires_appointment')->default(false);
            $table->boolean('allows_walk_in')->default(true);
            $table->boolean('taxable')->default(false);
            $table->boolean('queue_enabled')->default(false);
            $table->boolean('stock_related')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'code']);
            $table->unique(['facility_id', 'name']);
        });

        Schema::create('insurance_providers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('code', 40);
            $table->string('provider_type');
            $table->string('accreditation_number')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('claim_submission_method')->nullable();
            $table->unsignedInteger('payment_terms_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'code']);
        });

        Schema::create('corporate_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('code', 40);
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->decimal('credit_limit', 15, 2)->nullable();
            $table->unsignedInteger('payment_terms_days')->nullable();
            $table->string('billing_cycle')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'code']);
        });

        Schema::create('service_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->string('payer_type')->index();
            $table->foreignId('insurance_provider_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('corporate_account_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('currency', 10)->default('TZS');
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['facility_id', 'service_id', 'payer_type', 'is_active']);
        });

        Schema::create('patient_number_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year')->nullable();
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();
            $table->unique(['facility_id', 'year']);
        });

        Schema::create('visit_number_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year')->nullable();
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();
            $table->unique(['facility_id', 'year']);
        });

        Schema::create('invoice_number_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year')->nullable();
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();
            $table->unique(['facility_id', 'year']);
        });

        Schema::create('queue_number_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->date('queue_date');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
            $table->unique(['facility_id', 'department_id', 'queue_date']);
        });

        Schema::create('patients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->string('patient_number', 50);
            $table->string('first_name', 60);
            $table->string('middle_name', 60)->nullable();
            $table->string('last_name', 60);
            $table->string('gender');
            $table->date('date_of_birth')->nullable();
            $table->unsignedSmallInteger('age_years')->nullable();
            $table->unsignedTinyInteger('age_months')->nullable();
            $table->boolean('date_of_birth_is_estimated')->default(false);
            $table->string('marital_status')->nullable();
            $table->string('nationality')->default('Tanzanian');
            $table->string('nida_number', 40)->nullable();
            $table->string('passport_number', 40)->nullable();
            $table->string('primary_phone', 30)->nullable()->index();
            $table->string('secondary_phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->text('physical_address')->nullable();
            $table->text('postal_address')->nullable();
            $table->string('region')->nullable();
            $table->string('district')->nullable();
            $table->string('ward')->nullable();
            $table->string('street_or_village')->nullable();
            $table->string('occupation')->nullable();
            $table->string('religion')->nullable();
            $table->string('blood_group')->default('unknown');
            $table->string('rhesus_factor')->nullable();
            $table->text('known_allergies')->nullable();
            $table->text('chronic_conditions')->nullable();
            $table->string('disability_status')->nullable();
            $table->string('preferred_language', 10)->default('sw');
            $table->string('patient_status')->default('active')->index();
            $table->boolean('profile_incomplete')->default(false);
            $table->string('passport_photo_path')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('registered_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'patient_number']);
            $table->unique(['facility_id', 'nida_number']);
            $table->unique(['facility_id', 'passport_number']);
            $table->index(['facility_id', 'last_name']);
            $table->index(['facility_id', 'created_at']);
        });

        Schema::create('patient_contacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->string('contact_type');
            $table->string('full_name');
            $table->string('relationship')->nullable();
            $table->string('primary_phone', 30);
            $table->string('secondary_phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->text('physical_address')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('patient_payer_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->string('payer_type');
            $table->foreignId('insurance_provider_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('corporate_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('membership_number')->nullable();
            $table->string('card_number')->nullable();
            $table->string('principal_member_name')->nullable();
            $table->string('relationship_to_principal')->nullable();
            $table->string('authorization_number')->nullable();
            $table->string('policy_number')->nullable();
            $table->string('scheme_name')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->string('coverage_status')->default('unknown');
            $table->boolean('is_primary')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['facility_id', 'payer_type']);
        });

        Schema::create('patient_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->string('document_type');
            $table->string('document_name');
            $table->string('document_number')->nullable();
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('file_path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('visits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->string('visit_number', 50);
            $table->string('visit_type');
            $table->string('payer_type');
            $table->foreignId('patient_payer_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('destination_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('current_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('consultation_service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->string('visit_status')->index();
            $table->string('priority')->default('normal');
            $table->string('source')->default('walk_in');
            $table->text('reason_for_visit')->nullable();
            $table->string('referral_source')->nullable();
            $table->string('referral_number')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('registered_at');
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'visit_number']);
            $table->index(['facility_id', 'visit_status']);
        });

        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('invoice_number', 50);
            $table->string('payer_type');
            $table->foreignId('patient_payer_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->string('invoice_status')->default('pending');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('balance_amount', 15, 2)->default(0);
            $table->string('currency', 10)->default('TZS');
            $table->timestamp('issued_at');
            $table->timestamp('due_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'invoice_number']);
        });

        Schema::create('invoice_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('item_type');
            $table->string('description');
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2);
            $table->decimal('payer_amount', 15, 2);
            $table->decimal('insurance_amount', 15, 2)->default(0);
            $table->decimal('patient_amount', 15, 2)->default(0);
            $table->string('status')->default('pending');
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('patient_queues', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->string('queue_number');
            $table->date('queue_date');
            $table->string('queue_status')->default('waiting')->index();
            $table->string('priority')->default('normal');
            $table->unsignedInteger('position')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('called_at')->nullable();
            $table->timestamp('service_started_at')->nullable();
            $table->timestamp('service_completed_at')->nullable();
            $table->timestamp('skipped_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['facility_id', 'department_id', 'queue_date', 'queue_number']);
        });

        Schema::create('visit_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('to_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('movement_type');
            $table->string('status')->default('completed');
            $table->text('reason')->nullable();
            $table->foreignId('moved_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('moved_at');
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_movements');
        Schema::dropIfExists('patient_queues');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('visits');
        Schema::dropIfExists('patient_documents');
        Schema::dropIfExists('patient_payer_profiles');
        Schema::dropIfExists('patient_contacts');
        Schema::dropIfExists('patients');
        Schema::dropIfExists('queue_number_sequences');
        Schema::dropIfExists('invoice_number_sequences');
        Schema::dropIfExists('visit_number_sequences');
        Schema::dropIfExists('patient_number_sequences');
        Schema::dropIfExists('service_prices');
        Schema::dropIfExists('corporate_accounts');
        Schema::dropIfExists('insurance_providers');
        Schema::dropIfExists('services');
        Schema::dropIfExists('service_categories');
    }
};
