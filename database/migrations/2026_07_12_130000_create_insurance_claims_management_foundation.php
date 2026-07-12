<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('insurance_providers', function (Blueprint $table): void {
            if (! Schema::hasColumn('insurance_providers', 'registration_number')) $table->string('registration_number')->nullable()->after('provider_type');
            if (! Schema::hasColumn('insurance_providers', 'website')) $table->string('website')->nullable()->after('address');
            if (! Schema::hasColumn('insurance_providers', 'default_currency')) $table->string('default_currency', 10)->default('TZS')->after('payment_terms_days');
            if (! Schema::hasColumn('insurance_providers', 'requires_pre_authorization')) $table->boolean('requires_pre_authorization')->default(false)->after('default_currency');
            if (! Schema::hasColumn('insurance_providers', 'requires_referral')) $table->boolean('requires_referral')->default(false)->after('requires_pre_authorization');
            if (! Schema::hasColumn('insurance_providers', 'supports_dependants')) $table->boolean('supports_dependants')->default(true)->after('requires_referral');
            if (! Schema::hasColumn('insurance_providers', 'supports_copayment')) $table->boolean('supports_copayment')->default(true)->after('supports_dependants');
            if (! Schema::hasColumn('insurance_providers', 'supports_partial_approval')) $table->boolean('supports_partial_approval')->default(true)->after('supports_copayment');
            if (! Schema::hasColumn('insurance_providers', 'claim_prefix')) $table->string('claim_prefix', 20)->nullable()->after('supports_partial_approval');
            if (! Schema::hasColumn('insurance_providers', 'notes')) $table->text('notes')->nullable()->after('claim_prefix');
        });

        Schema::create('insurance_claim_number_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year')->nullable();
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();
            $table->unique(['facility_id', 'year'], 'ins_claim_seq_fac_year_uq');
        });

        Schema::create('insurance_claim_batch_number_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year')->nullable();
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();
            $table->unique(['facility_id', 'year'], 'ins_batch_seq_fac_year_uq');
        });

        Schema::create('insurance_schemes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('insurance_provider_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('code', 50);
            $table->text('description')->nullable();
            $table->string('scheme_type')->index();
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->foreignId('default_benefit_package_id')->nullable();
            $table->boolean('requires_membership_verification')->default(true);
            $table->boolean('requires_pre_authorization')->default(false);
            $table->boolean('requires_referral')->default(false);
            $table->boolean('allows_dependants')->default(true);
            $table->boolean('allows_copayment')->default(true);
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['insurance_provider_id', 'code'], 'ins_scheme_provider_code_uq');
        });

        Schema::create('insurance_benefit_packages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('insurance_provider_id')->constrained()->restrictOnDelete();
            $table->foreignId('insurance_scheme_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('code', 50);
            $table->text('description')->nullable();
            foreach (['annual', 'visit', 'inpatient', 'outpatient', 'dental', 'pharmacy', 'laboratory', 'rch', 'observation'] as $limit) {
                $table->decimal($limit.'_limit', 15, 2)->nullable();
            }
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['insurance_provider_id', 'code'], 'ins_pkg_provider_code_uq');
        });

        Schema::table('insurance_schemes', function (Blueprint $table): void {
            $table->foreign('default_benefit_package_id', 'ins_scheme_default_pkg_fk')->references('id')->on('insurance_benefit_packages')->nullOnDelete();
        });

        Schema::create('insurance_benefit_limits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('benefit_package_id')->constrained('insurance_benefit_packages')->cascadeOnDelete();
            $table->string('benefit_type')->index();
            $table->string('limit_type');
            $table->decimal('limit_amount', 15, 2)->nullable();
            $table->decimal('limit_quantity', 12, 3)->nullable();
            $table->string('period_type')->nullable();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('medicine_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('max_visits')->nullable();
            $table->unsignedInteger('max_days')->nullable();
            $table->boolean('requires_authorization')->default(false);
            $table->boolean('requires_referral')->default(false);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['facility_id', 'benefit_type'], 'ins_limit_fac_type_idx');
        });

        Schema::create('insurance_membership_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('insurance_provider_id')->constrained()->restrictOnDelete();
            $table->foreignId('insurance_scheme_id')->constrained()->restrictOnDelete();
            $table->foreignId('benefit_package_id')->nullable()->constrained('insurance_benefit_packages')->nullOnDelete();
            $table->string('name');
            $table->string('code', 50);
            $table->string('membership_type')->index();
            $table->unsignedInteger('waiting_period_days')->nullable();
            $table->unsignedInteger('dependent_limit')->nullable();
            $table->unsignedInteger('age_limit')->nullable();
            $table->string('copayment_type')->nullable();
            $table->decimal('copayment_value', 15, 2)->nullable();
            $table->decimal('coinsurance_percentage', 5, 2)->nullable();
            $table->decimal('deductible_amount', 15, 2)->nullable();
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['insurance_scheme_id', 'code'], 'ins_plan_scheme_code_uq');
        });

        Schema::create('patient_insurance_memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('insurance_provider_id')->constrained()->restrictOnDelete();
            $table->foreignId('insurance_scheme_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('benefit_package_id')->nullable()->constrained('insurance_benefit_packages')->nullOnDelete();
            $table->foreignId('membership_plan_id')->nullable()->constrained('insurance_membership_plans')->nullOnDelete();
            $table->string('membership_number');
            $table->string('principal_member_number')->nullable();
            $table->string('membership_type');
            $table->foreignId('principal_patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->string('employer_name')->nullable();
            $table->string('employer_number')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->string('verification_status')->default('unverified')->index();
            $table->timestamp('last_verified_at')->nullable();
            $table->string('verification_method')->nullable();
            $table->string('verification_reference')->nullable();
            $table->text('verification_notes')->nullable();
            $table->string('card_front_path')->nullable();
            $table->string('card_back_path')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'insurance_provider_id', 'membership_number'], 'ins_member_fac_provider_no_uq');
            $table->index(['facility_id', 'patient_id', 'is_primary'], 'ins_member_patient_primary_idx');
        });

        Schema::create('insurance_dependants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('principal_membership_id')->constrained('patient_insurance_memberships')->cascadeOnDelete();
            $table->foreignId('dependent_patient_id')->constrained('patients')->cascadeOnDelete();
            $table->string('relationship_type');
            $table->string('dependent_membership_number')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->string('verification_status')->default('unverified');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['principal_membership_id', 'dependent_patient_id'], 'ins_dependant_member_patient_uq');
        });

        Schema::create('insurance_verifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_id')->constrained('patient_insurance_memberships')->cascadeOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('verification_type')->index();
            $table->string('verification_method');
            $table->string('status')->index();
            $table->timestamp('verified_at');
            $table->timestamp('valid_until')->nullable();
            $table->string('reference_number')->nullable();
            $table->text('response_summary')->nullable();
            $table->foreignId('verified_by')->constrained('users')->restrictOnDelete();
            $table->text('override_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('insurance_coverage_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('insurance_provider_id')->constrained()->restrictOnDelete();
            $table->foreignId('insurance_scheme_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('benefit_package_id')->nullable()->constrained('insurance_benefit_packages')->nullOnDelete();
            $table->string('rule_scope')->index();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('medicine_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('diagnosis_code')->nullable();
            $table->string('coverage_status')->index();
            $table->decimal('coverage_percentage', 5, 2)->nullable();
            $table->string('patient_copayment_type')->nullable();
            $table->decimal('patient_copayment_value', 15, 2)->nullable();
            $table->decimal('maximum_quantity', 12, 3)->nullable();
            $table->decimal('maximum_amount', 15, 2)->nullable();
            $table->unsignedInteger('maximum_visits')->nullable();
            $table->boolean('requires_pre_authorization')->default(false);
            $table->boolean('requires_referral')->default(false);
            $table->unsignedInteger('waiting_period_days')->nullable();
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->text('exclusion_reason')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['facility_id', 'insurance_provider_id', 'rule_scope'], 'ins_cov_fac_provider_scope_idx');
        });

        Schema::create('insurance_contract_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('insurance_provider_id')->constrained()->restrictOnDelete();
            $table->foreignId('insurance_scheme_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('benefit_package_id')->nullable()->constrained('insurance_benefit_packages')->nullOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->decimal('price', 15, 2);
            $table->decimal('patient_amount', 15, 2)->nullable();
            $table->decimal('payer_amount', 15, 2)->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('authorization_required')->default(false);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['facility_id', 'service_id', 'is_active'], 'ins_price_fac_service_active_idx');
        });

        Schema::create('insurance_pre_authorizations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_id')->constrained('patient_insurance_memberships')->restrictOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('insurance_provider_id')->constrained()->restrictOnDelete();
            $table->foreignId('insurance_scheme_id')->nullable()->constrained()->nullOnDelete();
            $table->string('authorization_number')->nullable();
            $table->string('authorization_type')->index();
            $table->timestamp('requested_at');
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->decimal('requested_amount', 15, 2)->nullable();
            $table->decimal('approved_amount', 15, 2)->nullable();
            $table->string('status')->default('draft')->index();
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->date('response_date')->nullable();
            $table->string('approved_by_name')->nullable();
            $table->string('provider_reference')->nullable();
            $table->text('request_notes')->nullable();
            $table->text('response_notes')->nullable();
            $table->string('attachment_path')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('insurance_claim_rejection_reasons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('insurance_provider_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->string('category')->index();
            $table->text('description')->nullable();
            $table->text('correction_action')->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'insurance_provider_id', 'code'], 'ins_reject_fac_provider_code_uq');
        });

        Schema::create('insurance_claim_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('insurance_provider_id')->constrained()->restrictOnDelete();
            $table->foreignId('insurance_scheme_id')->nullable()->constrained()->nullOnDelete();
            $table->string('batch_number', 50);
            $table->date('batch_date');
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->string('status')->default('draft')->index();
            $table->unsignedInteger('claims_count')->default(0);
            $table->decimal('total_claimed_amount', 15, 2)->default(0);
            $table->decimal('total_approved_amount', 15, 2)->default(0);
            $table->decimal('total_paid_amount', 15, 2)->default(0);
            $table->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('prepared_at');
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->string('submission_reference')->nullable();
            $table->timestamp('response_received_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'batch_number'], 'ins_batch_fac_no_uq');
        });

        Schema::create('insurance_claims', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('insurance_provider_id')->constrained()->restrictOnDelete();
            $table->foreignId('insurance_scheme_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('benefit_package_id')->nullable()->constrained('insurance_benefit_packages')->nullOnDelete();
            $table->foreignId('membership_id')->constrained('patient_insurance_memberships')->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->string('claim_number', 50);
            $table->string('external_claim_number')->nullable();
            $table->string('claim_type')->index();
            $table->date('service_date_from');
            $table->date('service_date_to');
            $table->string('status')->default('draft')->index();
            $table->string('currency', 10)->default('TZS');
            $table->decimal('gross_amount', 15, 2)->default(0);
            $table->decimal('patient_amount', 15, 2)->default(0);
            $table->decimal('payer_claimed_amount', 15, 2)->default(0);
            $table->decimal('approved_amount', 15, 2)->nullable();
            $table->decimal('rejected_amount', 15, 2)->nullable();
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('outstanding_amount', 15, 2)->default(0);
            $table->text('diagnosis_summary')->nullable();
            $table->string('primary_diagnosis_code')->nullable();
            $table->foreignId('authorization_id')->nullable()->constrained('insurance_pre_authorizations')->nullOnDelete();
            $table->foreignId('referral_id')->nullable()->constrained('patient_referrals')->nullOnDelete();
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('prepared_at')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('rejection_reason_id')->nullable()->constrained('insurance_claim_rejection_reasons')->nullOnDelete();
            $table->text('rejection_notes')->nullable();
            $table->text('correction_reason')->nullable();
            $table->unsignedInteger('resubmission_count')->default(0);
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('parent_claim_id')->nullable()->constrained('insurance_claims')->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('insurance_claim_batches')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'claim_number'], 'ins_claim_fac_no_uq');
            $table->index(['facility_id', 'status', 'submitted_at'], 'ins_claim_fac_status_sub_idx');
        });

        Schema::create('insurance_claim_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('insurance_claim_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('medicine_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('laboratory_order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('dental_procedure_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('observation_reference_id')->nullable();
            $table->string('rch_reference_type')->nullable();
            $table->unsignedBigInteger('rch_reference_id')->nullable();
            $table->string('item_type')->index();
            $table->string('service_code_snapshot')->nullable();
            $table->string('payer_service_code')->nullable();
            $table->string('description_snapshot');
            $table->date('service_date')->index();
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('gross_amount', 15, 2);
            $table->decimal('patient_amount', 15, 2);
            $table->decimal('claimed_amount', 15, 2);
            $table->decimal('approved_amount', 15, 2)->nullable();
            $table->decimal('rejected_amount', 15, 2)->nullable();
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->string('diagnosis_code')->nullable();
            $table->string('procedure_code')->nullable();
            $table->string('medicine_code')->nullable();
            $table->string('coverage_status')->default('not_configured');
            $table->string('authorization_number')->nullable();
            $table->string('status')->default('draft')->index();
            $table->foreignId('rejection_reason_id')->nullable()->constrained('insurance_claim_rejection_reasons')->nullOnDelete();
            $table->text('rejection_notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['insurance_claim_id', 'invoice_item_id'], 'ins_claim_item_invoice_uq');
        });

        Schema::create('diagnosis_codes', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('coding_system')->default('ICD10');
            $table->string('parent_code')->nullable();
            $table->boolean('is_billable')->default(true);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('insurance_claim_diagnoses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('insurance_claim_id')->constrained()->cascadeOnDelete();
            $table->foreignId('diagnosis_id')->nullable()->constrained()->nullOnDelete();
            $table->string('diagnosis_code');
            $table->string('diagnosis_name_snapshot');
            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('sequence_order')->default(0);
            $table->timestamps();
        });

        Schema::create('insurance_service_code_mappings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('insurance_provider_id')->constrained()->restrictOnDelete();
            $table->foreignId('insurance_scheme_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->string('payer_service_code');
            $table->string('payer_service_name')->nullable();
            $table->string('procedure_code')->nullable();
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['insurance_provider_id', 'insurance_scheme_id', 'service_id'], 'ins_svc_map_provider_scheme_service_uq');
        });

        Schema::create('insurance_medicine_code_mappings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('insurance_provider_id')->constrained()->restrictOnDelete();
            $table->foreignId('insurance_scheme_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('medicine_id')->constrained()->restrictOnDelete();
            $table->string('payer_medicine_code');
            $table->string('payer_medicine_name')->nullable();
            $table->string('dispensing_unit_snapshot')->nullable();
            $table->decimal('maximum_quantity', 12, 3)->nullable();
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['insurance_provider_id', 'insurance_scheme_id', 'medicine_id'], 'ins_med_map_provider_scheme_med_uq');
        });

        Schema::create('insurance_claim_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('claim_batch_id')->constrained('insurance_claim_batches')->cascadeOnDelete();
            $table->foreignId('insurance_provider_id')->constrained()->restrictOnDelete();
            $table->string('submission_method');
            $table->string('submission_reference')->nullable();
            $table->timestamp('submitted_at');
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->string('status')->default('prepared');
            $table->string('acknowledgement_reference')->nullable();
            $table->timestamp('acknowledgement_at')->nullable();
            $table->string('package_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('insurance_claim_responses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('insurance_claim_id')->constrained()->cascadeOnDelete();
            $table->foreignId('claim_batch_id')->nullable()->constrained('insurance_claim_batches')->nullOnDelete();
            $table->string('response_reference')->nullable();
            $table->date('response_date');
            $table->string('response_status');
            $table->decimal('approved_amount', 15, 2)->default(0);
            $table->decimal('rejected_amount', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->string('response_file_path')->nullable();
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('insurance_claim_item_responses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('claim_response_id')->constrained('insurance_claim_responses')->cascadeOnDelete();
            $table->foreignId('insurance_claim_item_id')->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->decimal('approved_amount', 15, 2)->default(0);
            $table->decimal('rejected_amount', 15, 2)->default(0);
            $table->foreignId('rejection_reason_id')->nullable()->constrained('insurance_claim_rejection_reasons')->nullOnDelete();
            $table->string('rejection_code')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('insurance_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('insurance_provider_id')->constrained()->restrictOnDelete();
            $table->foreignId('insurance_scheme_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payment_reference');
            $table->date('payment_date')->index();
            $table->decimal('amount', 15, 2);
            $table->string('currency', 10)->default('TZS');
            $table->string('payment_method');
            $table->string('bank_reference')->nullable();
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->string('status')->default('received')->index();
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->string('attachment_path')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'payment_reference'], 'ins_payment_fac_ref_uq');
        });

        Schema::create('insurance_payment_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('insurance_payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('insurance_claim_id')->constrained()->cascadeOnDelete();
            $table->foreignId('insurance_claim_item_id')->nullable()->constrained()->cascadeOnDelete();
            $table->decimal('allocated_amount', 15, 2);
            $table->foreignId('allocated_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('allocated_at');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('insurance_claim_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('insurance_claim_id')->constrained()->cascadeOnDelete();
            $table->string('attachment_type');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('file_path');
            $table->string('mime_type');
            $table->unsignedBigInteger('file_size');
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('uploaded_at');
            $table->boolean('is_required')->default(false);
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('insurance_claim_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('insurance_claim_id')->constrained()->cascadeOnDelete();
            $table->string('note_type');
            $table->text('note');
            $table->boolean('is_internal')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('insurance_claim_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('insurance_provider_id')->constrained()->restrictOnDelete();
            $table->foreignId('insurance_scheme_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('claim_submission_days')->nullable();
            $table->unsignedInteger('correction_submission_days')->nullable();
            $table->unsignedInteger('resubmission_days')->nullable();
            $table->decimal('minimum_claim_amount', 15, 2)->nullable();
            $table->decimal('maximum_claim_amount', 15, 2)->nullable();
            $table->boolean('requires_primary_diagnosis')->default(true);
            $table->boolean('requires_service_codes')->default(true);
            $table->boolean('requires_provider_signature')->default(false);
            $table->boolean('requires_facility_stamp')->default(false);
            $table->boolean('requires_invoice_attachment')->default(false);
            $table->boolean('requires_prescription_attachment')->default(false);
            $table->boolean('requires_lab_report_attachment')->default(false);
            $table->boolean('requires_referral_attachment')->default(false);
            $table->boolean('requires_authorization_attachment')->default(false);
            $table->json('other_requirements')->nullable();
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('invoice_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('invoice_items', 'insurance_provider_id')) $table->foreignId('insurance_provider_id')->nullable()->after('service_id')->constrained()->nullOnDelete();
            if (! Schema::hasColumn('invoice_items', 'insurance_scheme_id')) $table->foreignId('insurance_scheme_id')->nullable()->after('insurance_provider_id')->constrained()->nullOnDelete();
            if (! Schema::hasColumn('invoice_items', 'patient_insurance_membership_id')) $table->foreignId('patient_insurance_membership_id')->nullable()->after('insurance_scheme_id')->constrained()->nullOnDelete();
            if (! Schema::hasColumn('invoice_items', 'insurance_pre_authorization_id')) $table->foreignId('insurance_pre_authorization_id')->nullable()->after('patient_insurance_membership_id')->constrained()->nullOnDelete();
            if (! Schema::hasColumn('invoice_items', 'insurance_referral_id')) $table->foreignId('insurance_referral_id')->nullable()->after('insurance_pre_authorization_id')->constrained('patient_referrals')->nullOnDelete();
            if (! Schema::hasColumn('invoice_items', 'coverage_percentage')) $table->decimal('coverage_percentage', 5, 2)->nullable()->after('insurance_amount');
            if (! Schema::hasColumn('invoice_items', 'claimable_status')) $table->string('claimable_status')->default('not_evaluated')->after('coverage_percentage');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table): void {
            foreach (['insurance_provider_id', 'insurance_scheme_id', 'patient_insurance_membership_id', 'insurance_pre_authorization_id', 'insurance_referral_id'] as $column) {
                if (Schema::hasColumn('invoice_items', $column)) $table->dropConstrainedForeignId($column);
            }
            foreach (['coverage_percentage', 'claimable_status'] as $column) {
                if (Schema::hasColumn('invoice_items', $column)) $table->dropColumn($column);
            }
        });

        Schema::dropIfExists('insurance_claim_rules');
        Schema::dropIfExists('insurance_claim_notes');
        Schema::dropIfExists('insurance_claim_attachments');
        Schema::dropIfExists('insurance_payment_allocations');
        Schema::dropIfExists('insurance_payments');
        Schema::dropIfExists('insurance_claim_item_responses');
        Schema::dropIfExists('insurance_claim_responses');
        Schema::dropIfExists('insurance_claim_submissions');
        Schema::dropIfExists('insurance_medicine_code_mappings');
        Schema::dropIfExists('insurance_service_code_mappings');
        Schema::dropIfExists('insurance_claim_diagnoses');
        Schema::dropIfExists('diagnosis_codes');
        Schema::dropIfExists('insurance_claim_items');
        Schema::dropIfExists('insurance_claims');
        Schema::dropIfExists('insurance_claim_batches');
        Schema::dropIfExists('insurance_claim_rejection_reasons');
        Schema::dropIfExists('insurance_pre_authorizations');
        Schema::dropIfExists('insurance_contract_prices');
        Schema::dropIfExists('insurance_coverage_rules');
        Schema::dropIfExists('insurance_verifications');
        Schema::dropIfExists('insurance_dependants');
        Schema::dropIfExists('patient_insurance_memberships');
        Schema::dropIfExists('insurance_membership_plans');
        Schema::dropIfExists('insurance_benefit_limits');
        Schema::table('insurance_schemes', fn (Blueprint $table) => $table->dropForeign('ins_scheme_default_pkg_fk'));
        Schema::dropIfExists('insurance_benefit_packages');
        Schema::dropIfExists('insurance_schemes');
        Schema::dropIfExists('insurance_claim_batch_number_sequences');
        Schema::dropIfExists('insurance_claim_number_sequences');

        Schema::table('insurance_providers', function (Blueprint $table): void {
            foreach (['registration_number', 'website', 'default_currency', 'requires_pre_authorization', 'requires_referral', 'supports_dependants', 'supports_copayment', 'supports_partial_approval', 'claim_prefix', 'notes'] as $column) {
                if (Schema::hasColumn('insurance_providers', $column)) $table->dropColumn($column);
            }
        });
    }
};
