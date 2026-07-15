<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->dropRchTables();

        foreach (['rch_encounter_sequences', 'pregnancy_sequences', 'family_planning_sequences', 'rch_child_sequences'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table): void {
                $table->id();
                $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
                $table->unsignedSmallInteger('year')->nullable();
                $table->unsignedBigInteger('last_number')->default(0);
                $table->timestamps();
                $table->unique(['facility_id', 'year']);
            });
        }

        Schema::create('rch_encounters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('clinical_encounter_id')->nullable()->constrained()->nullOnDelete();
            $table->string('encounter_type', 40);
            $table->string('encounter_number', 40);
            $table->foreignId('provider_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 40)->default('waiting')->index();
            $table->text('chief_complaint')->nullable();
            $table->text('clinical_summary')->nullable();
            $table->text('treatment_plan')->nullable();
            $table->boolean('follow_up_required')->default(false);
            $table->date('follow_up_date')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('signed_off_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('signed_off_at')->nullable();
            $table->text('amendment_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'encounter_number'], 'rch_enc_fac_no_uq');
        });

        Schema::create('pregnancies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->string('pregnancy_number', 40);
            $table->string('status', 40)->default('active')->index();
            $table->date('lmp_date')->nullable();
            $table->boolean('lmp_is_certain')->default(true);
            $table->date('estimated_delivery_date')->nullable();
            $table->string('dating_method', 40)->nullable();
            $table->unsignedSmallInteger('gravida')->nullable();
            $table->unsignedSmallInteger('para')->nullable();
            $table->unsignedSmallInteger('term_births')->nullable();
            $table->unsignedSmallInteger('preterm_births')->nullable();
            $table->unsignedSmallInteger('abortions')->nullable();
            $table->unsignedSmallInteger('living_children')->nullable();
            $table->boolean('multiple_pregnancy')->default(false);
            $table->unsignedSmallInteger('number_of_fetuses')->nullable();
            $table->string('blood_group_snapshot', 10)->nullable();
            $table->string('rhesus_factor_snapshot', 10)->nullable();
            $table->decimal('booking_weight_kg', 6, 2)->nullable();
            $table->decimal('booking_height_cm', 6, 2)->nullable();
            $table->decimal('booking_bmi', 6, 2)->nullable();
            $table->boolean('high_risk')->default(false)->index();
            $table->string('risk_level', 30)->nullable();
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('registered_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('outcome', 50)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'pregnancy_number'], 'preg_fac_no_uq');
        });

        Schema::create('pregnancy_dating_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pregnancy_id')->constrained()->cascadeOnDelete();
            $table->string('dating_method', 40);
            $table->date('reference_date');
            $table->unsignedSmallInteger('gestational_age_weeks')->nullable();
            $table->unsignedSmallInteger('gestational_age_days')->nullable();
            $table->date('calculated_edd');
            $table->boolean('is_primary')->default(false);
            $table->text('reason')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('recorded_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('obstetric_history_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pregnancy_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('sequence_number');
            $table->unsignedSmallInteger('pregnancy_year')->nullable();
            $table->string('outcome', 40);
            $table->unsignedSmallInteger('gestational_age_weeks')->nullable();
            $table->string('delivery_mode', 80)->nullable();
            $table->string('place_of_delivery')->nullable();
            $table->string('child_sex', 20)->nullable();
            $table->decimal('birth_weight_kg', 5, 2)->nullable();
            $table->string('child_status', 80)->nullable();
            $table->text('maternal_complications')->nullable();
            $table->text('neonatal_complications')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('anc_registrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pregnancy_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->date('registration_date');
            $table->unsignedSmallInteger('registration_gestational_age_weeks')->nullable();
            $table->string('referral_source')->nullable();
            $table->string('previous_anc_facility')->nullable();
            $table->string('anc_card_number')->nullable();
            $table->string('booking_status', 40)->default('unknown');
            $table->foreignId('enrolled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('anc_visits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pregnancy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('rch_encounter_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('anc_visit_number');
            $table->date('visit_date');
            $table->unsignedSmallInteger('gestational_age_weeks');
            $table->unsignedSmallInteger('gestational_age_days')->nullable();
            $table->decimal('weight_kg', 6, 2)->nullable();
            $table->unsignedSmallInteger('systolic_bp')->nullable();
            $table->unsignedSmallInteger('diastolic_bp')->nullable();
            $table->unsignedSmallInteger('pulse_rate')->nullable();
            $table->decimal('temperature', 4, 1)->nullable();
            $table->unsignedSmallInteger('respiratory_rate')->nullable();
            $table->unsignedSmallInteger('oxygen_saturation')->nullable();
            $table->string('urine_protein', 40)->nullable();
            $table->string('urine_glucose', 40)->nullable();
            $table->decimal('hemoglobin', 4, 1)->nullable();
            $table->decimal('fundal_height_cm', 5, 1)->nullable();
            $table->unsignedSmallInteger('fetal_heart_rate')->nullable();
            $table->string('fetal_movement', 40)->nullable();
            $table->string('presentation', 60)->nullable();
            $table->string('lie', 60)->nullable();
            $table->boolean('edema')->nullable();
            $table->boolean('pallor')->nullable();
            $table->json('danger_signs')->nullable();
            $table->text('complaints')->nullable();
            $table->text('examination_findings')->nullable();
            $table->text('assessment')->nullable();
            $table->text('plan')->nullable();
            $table->string('risk_level', 30)->nullable();
            $table->date('next_visit_date')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 40)->default('draft');
            $table->foreignId('signed_off_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('signed_off_at')->nullable();
            $table->text('amendment_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('pregnancy_risk_factor_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('code', 60);
            $table->string('name');
            $table->string('category', 60);
            $table->string('severity', 30)->default('moderate');
            $table->text('description')->nullable();
            $table->boolean('referral_recommended')->default(false);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'code'], 'preg_risk_type_fac_code_uq');
        });

        Schema::create('pregnancy_risk_factors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pregnancy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('anc_visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('risk_factor_type_id')->constrained('pregnancy_risk_factor_types')->cascadeOnDelete();
            $table->string('severity', 30)->default('moderate');
            $table->string('status', 30)->default('active')->index();
            $table->text('details')->nullable();
            $table->foreignId('detected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('detected_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('birth_preparedness_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pregnancy_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('preferred_delivery_facility')->nullable();
            $table->boolean('skilled_provider_identified')->default(false);
            $table->text('transport_plan')->nullable();
            $table->string('emergency_transport_contact')->nullable();
            $table->boolean('funds_prepared')->default(false);
            $table->boolean('blood_donor_identified')->default(false);
            $table->string('birth_companion')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->boolean('danger_signs_counselling_done')->default(false);
            $table->boolean('delivery_supplies_prepared')->default(false);
            $table->text('referral_plan')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('prepared_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('pmtct_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pregnancy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->string('hiv_test_status', 40);
            $table->date('test_date')->nullable();
            $table->string('result_status', 40);
            $table->string('disclosure_status', 80)->nullable();
            $table->string('partner_testing_status', 80)->nullable();
            $table->string('linkage_status', 80)->nullable();
            $table->string('referral_facility')->nullable();
            $table->date('referral_date')->nullable();
            $table->text('confidential_notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('family_planning_methods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 60);
            $table->string('category', 80);
            $table->unsignedInteger('duration_days')->nullable();
            $table->boolean('requires_procedure')->default(false);
            $table->boolean('requires_prescription')->default(false);
            $table->boolean('requires_inventory_item')->default(false);
            $table->foreignId('medicine_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->text('contraindications')->nullable();
            $table->text('common_side_effects')->nullable();
            $table->text('counselling_points')->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'code'], 'fp_method_fac_code_uq');
        });

        Schema::create('family_planning_clients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->string('fp_client_number', 40);
            $table->date('registration_date');
            $table->string('client_type', 60);
            $table->string('reproductive_intention')->nullable();
            $table->unsignedSmallInteger('desired_number_of_children')->nullable();
            $table->string('spacing_preference')->nullable();
            $table->foreignId('current_method_id')->nullable()->constrained('family_planning_methods')->nullOnDelete();
            $table->string('status', 40)->default('active');
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'fp_client_number'], 'fp_client_fac_no_uq');
        });

        Schema::create('family_planning_visits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('family_planning_client_id')->constrained(indexName: 'fp_visit_client_fk')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('rch_encounter_id')->nullable()->constrained()->nullOnDelete();
            $table->date('visit_date');
            $table->string('visit_type', 60);
            $table->foreignId('current_method_id')->nullable()->constrained('family_planning_methods')->nullOnDelete();
            $table->foreignId('selected_method_id')->nullable()->constrained('family_planning_methods')->nullOnDelete();
            $table->date('method_start_date')->nullable();
            $table->date('expected_end_date')->nullable();
            $table->decimal('weight_kg', 6, 2)->nullable();
            $table->unsignedSmallInteger('systolic_bp')->nullable();
            $table->unsignedSmallInteger('diastolic_bp')->nullable();
            $table->string('pregnancy_test_status', 40)->nullable();
            $table->boolean('counselling_done')->default(false);
            $table->text('eligibility_assessment')->nullable();
            $table->text('side_effects')->nullable();
            $table->text('complications')->nullable();
            $table->boolean('method_changed')->default(false);
            $table->foreignId('previous_method_id')->nullable()->constrained('family_planning_methods')->nullOnDelete();
            $table->text('discontinuation_reason')->nullable();
            $table->date('next_visit_date')->nullable();
            $table->foreignId('provided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 40)->default('draft');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('family_planning_method_episodes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('family_planning_client_id')->constrained(indexName: 'fp_episode_client_fk')->cascadeOnDelete();
            $table->foreignId('method_id')->constrained('family_planning_methods')->cascadeOnDelete();
            $table->date('started_at');
            $table->date('expected_end_at')->nullable();
            $table->date('ended_at')->nullable();
            $table->string('status', 40)->default('active');
            $table->text('discontinuation_reason')->nullable();
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('ended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('source_visit_id')->nullable()->constrained('family_planning_visits')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('rch_children', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('child_patient_id')->unique()->constrained('patients')->cascadeOnDelete();
            $table->foreignId('mother_patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->foreignId('father_patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->foreignId('guardian_patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->string('child_rch_number', 40);
            $table->string('birth_registration_number')->nullable();
            $table->date('birth_date');
            $table->time('birth_time')->nullable();
            $table->string('birth_place')->nullable();
            $table->string('birth_type', 60)->nullable();
            $table->unsignedSmallInteger('birth_order')->nullable();
            $table->unsignedSmallInteger('gestational_age_at_birth_weeks')->nullable();
            $table->decimal('birth_weight_kg', 5, 2)->nullable();
            $table->decimal('birth_length_cm', 5, 1)->nullable();
            $table->decimal('head_circumference_at_birth_cm', 5, 1)->nullable();
            $table->string('sex_at_birth', 20);
            $table->string('delivery_mode', 80)->nullable();
            $table->unsignedSmallInteger('apgar_1_minute')->nullable();
            $table->unsignedSmallInteger('apgar_5_minutes')->nullable();
            $table->text('neonatal_complications')->nullable();
            $table->string('feeding_method', 60)->nullable();
            $table->string('status', 40)->default('active');
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('registered_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'child_rch_number'], 'rch_child_fac_no_uq');
        });

        Schema::create('patient_relationships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('related_patient_id')->constrained('patients')->cascadeOnDelete();
            $table->string('relationship_type', 40);
            $table->boolean('is_primary')->default(false);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'patient_id', 'related_patient_id', 'relationship_type'], 'pat_rel_unique');
        });

        Schema::create('child_growth_measurements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rch_child_id')->constrained('rch_children')->cascadeOnDelete();
            $table->foreignId('child_patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('rch_encounter_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('measured_at');
            $table->unsignedInteger('age_in_days');
            $table->decimal('weight_kg', 6, 2)->nullable();
            $table->decimal('length_height_cm', 6, 2)->nullable();
            $table->string('measurement_position', 30)->nullable();
            $table->decimal('head_circumference_cm', 5, 1)->nullable();
            $table->decimal('muac_cm', 5, 1)->nullable();
            $table->decimal('bmi', 6, 2)->nullable();
            $table->boolean('edema_present')->default(false);
            $table->string('feeding_method', 60)->nullable();
            $table->string('appetite_status', 60)->nullable();
            $table->text('illness_since_last_visit')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->text('amendment_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('growth_reference_standards', function (Blueprint $table): void {
            $table->id();
            $table->string('standard_name');
            $table->string('sex', 20);
            $table->string('indicator', 80);
            $table->decimal('age_or_length_value', 8, 2);
            $table->string('age_or_length_unit', 20);
            $table->decimal('l_value', 10, 5)->nullable();
            $table->decimal('m_value', 10, 5)->nullable();
            $table->decimal('s_value', 10, 5)->nullable();
            $table->decimal('sd0', 10, 3)->nullable();
            $table->decimal('sd1', 10, 3)->nullable();
            $table->decimal('sd2', 10, 3)->nullable();
            $table->decimal('sd3', 10, 3)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('child_nutrition_assessments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rch_child_id')->constrained('rch_children')->cascadeOnDelete();
            $table->foreignId('child_growth_measurement_id')->constrained(indexName: 'child_nutrition_growth_fk')->cascadeOnDelete();
            $table->date('assessment_date');
            $table->string('weight_for_age_classification')->nullable();
            $table->string('height_for_age_classification')->nullable();
            $table->string('weight_for_height_classification')->nullable();
            $table->string('bmi_for_age_classification')->nullable();
            $table->string('muac_classification')->nullable();
            $table->string('edema_classification')->nullable();
            $table->string('overall_nutrition_status', 60)->default('indeterminate');
            $table->text('feeding_counselling')->nullable();
            $table->text('nutrition_plan')->nullable();
            $table->boolean('referral_required')->default(false);
            $table->foreignId('referral_id')->nullable()->constrained('patient_referrals')->nullOnDelete();
            $table->foreignId('assessed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('vaccines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 60);
            $table->string('disease_prevented')->nullable();
            $table->foreignId('route_id')->nullable()->constrained('medicine_routes')->nullOnDelete();
            $table->decimal('dosage', 8, 3)->nullable();
            $table->string('dosage_unit', 30)->nullable();
            $table->string('storage_temperature')->nullable();
            $table->boolean('multi_dose_vial')->default(false);
            $table->unsignedSmallInteger('doses_per_vial')->nullable();
            $table->boolean('requires_diluent')->default(false);
            $table->unsignedInteger('minimum_age_days')->nullable();
            $table->unsignedInteger('maximum_age_days')->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('medicine_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'code'], 'vacc_fac_code_uq');
        });

        Schema::create('immunization_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 60);
            $table->text('description')->nullable();
            $table->string('target_group', 60);
            $table->boolean('is_default')->default(false);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'code'], 'imm_sched_fac_code_uq');
        });

        Schema::create('immunization_schedule_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('immunization_schedule_id')->constrained(indexName: 'imm_item_schedule_fk')->cascadeOnDelete();
            $table->foreignId('vaccine_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('dose_number');
            $table->string('dose_name');
            $table->unsignedInteger('recommended_age_days')->nullable();
            $table->unsignedInteger('minimum_age_days')->nullable();
            $table->unsignedInteger('maximum_age_days')->nullable();
            $table->unsignedInteger('minimum_interval_days')->nullable();
            $table->unsignedInteger('maximum_interval_days')->nullable();
            $table->string('route_snapshot')->nullable();
            $table->string('dosage_snapshot')->nullable();
            $table->boolean('is_required')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['immunization_schedule_id', 'vaccine_id', 'dose_number'], 'imm_item_sched_vacc_dose_uq');
        });

        Schema::create('vaccine_batch_details', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('medicine_batch_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('vaccine_id')->constrained()->cascadeOnDelete();
            $table->string('vial_monitor_status')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('discard_at')->nullable();
            $table->decimal('doses_remaining', 8, 3)->nullable();
            $table->string('cold_chain_status', 40)->default('unknown');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('immunization_administrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rch_child_id')->nullable()->constrained('rch_children')->nullOnDelete();
            $table->foreignId('pregnancy_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('rch_encounter_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('immunization_schedule_item_id')->nullable()->constrained(indexName: 'imm_admin_schedule_item_fk')->nullOnDelete();
            $table->foreignId('vaccine_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('dose_number')->nullable();
            $table->string('dose_name_snapshot');
            $table->date('administration_date');
            $table->unsignedInteger('age_at_administration_days')->nullable();
            $table->foreignId('medicine_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('batch_number_snapshot')->nullable();
            $table->date('expiry_date_snapshot')->nullable();
            $table->decimal('dose_quantity', 8, 3)->nullable();
            $table->string('dose_unit', 30)->nullable();
            $table->string('route_snapshot')->nullable();
            $table->string('administration_site')->nullable();
            $table->foreignId('administered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 40)->default('administered')->index();
            $table->text('reason_not_given')->nullable();
            $table->text('adverse_event')->nullable();
            $table->date('next_due_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('invoice_item_id')->nullable()->constrained('invoice_items')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        $this->dropRchTables();
    }

    private function dropRchTables(): void
    {
        Schema::dropIfExists('immunization_administrations');
        Schema::dropIfExists('vaccine_batch_details');
        Schema::dropIfExists('immunization_schedule_items');
        Schema::dropIfExists('immunization_schedules');
        Schema::dropIfExists('vaccines');
        Schema::dropIfExists('child_nutrition_assessments');
        Schema::dropIfExists('growth_reference_standards');
        Schema::dropIfExists('child_growth_measurements');
        Schema::dropIfExists('patient_relationships');
        Schema::dropIfExists('rch_children');
        Schema::dropIfExists('family_planning_method_episodes');
        Schema::dropIfExists('family_planning_visits');
        Schema::dropIfExists('family_planning_clients');
        Schema::dropIfExists('family_planning_methods');
        Schema::dropIfExists('pmtct_records');
        Schema::dropIfExists('birth_preparedness_plans');
        Schema::dropIfExists('pregnancy_risk_factors');
        Schema::dropIfExists('pregnancy_risk_factor_types');
        Schema::dropIfExists('anc_visits');
        Schema::dropIfExists('anc_registrations');
        Schema::dropIfExists('obstetric_history_records');
        Schema::dropIfExists('pregnancy_dating_records');
        Schema::dropIfExists('pregnancies');
        Schema::dropIfExists('rch_encounters');
        Schema::dropIfExists('rch_child_sequences');
        Schema::dropIfExists('family_planning_sequences');
        Schema::dropIfExists('pregnancy_sequences');
        Schema::dropIfExists('rch_encounter_sequences');
    }
};
