<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('observation_admission_number_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('year');
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();
            $table->unique(['facility_id', 'year']);
        });

        Schema::create('observation_rooms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('code', 50);
            $table->string('room_type')->index();
            $table->string('floor')->nullable();
            $table->text('location_description')->nullable();
            $table->string('gender_restriction')->nullable()->index();
            $table->boolean('isolation_room')->default(false);
            $table->unsignedInteger('capacity')->default(1);
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'code']);
            $table->unique(['facility_id', 'name']);
        });

        Schema::create('beds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('observation_room_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('code', 50);
            $table->string('bed_type')->index();
            $table->string('gender_restriction')->nullable()->index();
            $table->decimal('hourly_rate', 15, 2)->nullable();
            $table->decimal('session_rate', 15, 2)->nullable();
            $table->decimal('daily_rate', 15, 2)->nullable();
            $table->string('status')->index();
            $table->boolean('is_active')->default(true)->index();
            $table->string('current_cleaning_status')->nullable()->index();
            $table->dateTime('last_cleaned_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'code']);
            $table->index(['facility_id', 'status', 'is_active']);
        });

        Schema::create('observation_admissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->constrained()->restrictOnDelete();
            $table->foreignId('clinical_encounter_id')->nullable()->constrained()->nullOnDelete();
            $table->string('admission_number', 60);
            $table->string('admission_type')->index();
            $table->text('reason_for_admission');
            $table->text('provisional_diagnosis')->nullable();
            $table->text('final_diagnosis')->nullable();
            $table->foreignId('admitted_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('admitted_at')->index();
            $table->dateTime('expected_discharge_at')->nullable()->index();
            $table->dateTime('actual_discharge_at')->nullable();
            $table->foreignId('current_bed_id')->nullable()->constrained('beds')->nullOnDelete();
            $table->foreignId('current_room_id')->nullable()->constrained('observation_rooms')->nullOnDelete();
            $table->string('payer_type')->index();
            $table->foreignId('patient_payer_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->index();
            $table->string('acuity_level')->nullable()->index();
            $table->boolean('isolation_required')->default(false);
            $table->boolean('guardian_required')->default(false);
            $table->string('guardian_name')->nullable();
            $table->string('guardian_phone')->nullable();
            $table->text('diet_instruction')->nullable();
            $table->string('mobility_status')->nullable();
            $table->string('fall_risk')->nullable();
            $table->string('infection_risk')->nullable();
            $table->text('allergies_snapshot')->nullable();
            $table->text('chronic_conditions_snapshot')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'admission_number']);
            $table->index(['facility_id', 'patient_id', 'status']);
            $table->index(['facility_id', 'current_bed_id']);
        });

        Schema::create('bed_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('observation_admission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('bed_id')->constrained()->restrictOnDelete();
            $table->foreignId('room_id')->constrained('observation_rooms')->restrictOnDelete();
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('assigned_at')->index();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('released_at')->nullable();
            $table->string('assignment_status')->index();
            $table->text('transfer_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['facility_id', 'bed_id', 'assignment_status']);
            $table->index(['facility_id', 'observation_admission_id', 'assignment_status'], 'bed_assign_fac_adm_status_idx');
        });

        Schema::create('bed_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->constrained()->restrictOnDelete();
            $table->foreignId('admission_id')->nullable()->constrained('observation_admissions')->nullOnDelete();
            $table->foreignId('bed_id')->constrained()->restrictOnDelete();
            $table->foreignId('reserved_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('reserved_at')->index();
            $table->dateTime('expires_at')->nullable()->index();
            $table->string('status')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['facility_id', 'bed_id', 'status']);
        });

        Schema::create('nursing_observations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('observation_admission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->constrained()->restrictOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('recorded_at')->index();
            $table->string('general_condition')->nullable();
            $table->string('consciousness_level')->nullable();
            $table->unsignedTinyInteger('pain_score')->nullable();
            $table->decimal('temperature', 5, 2)->nullable();
            $table->unsignedSmallInteger('systolic_bp')->nullable();
            $table->unsignedSmallInteger('diastolic_bp')->nullable();
            $table->unsignedSmallInteger('pulse_rate')->nullable();
            $table->unsignedSmallInteger('respiratory_rate')->nullable();
            $table->decimal('oxygen_saturation', 5, 2)->nullable();
            $table->decimal('blood_glucose', 8, 2)->nullable();
            $table->text('intake_summary')->nullable();
            $table->text('output_summary')->nullable();
            $table->string('mobility_status')->nullable();
            $table->string('fall_risk')->nullable();
            $table->string('skin_condition')->nullable();
            $table->string('wound_status')->nullable();
            $table->string('nausea_vomiting')->nullable();
            $table->string('bowel_status')->nullable();
            $table->string('urine_status')->nullable();
            $table->longText('notes')->nullable();
            $table->string('status')->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['facility_id', 'observation_admission_id', 'recorded_at'], 'nurse_obs_fac_adm_time_idx');
        });

        Schema::create('observation_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('observation_admission_id')->constrained()->cascadeOnDelete();
            $table->string('schedule_type')->index();
            $table->unsignedInteger('interval_minutes')->nullable();
            $table->dateTime('next_due_at')->nullable()->index();
            $table->dateTime('starts_at')->index();
            $table->dateTime('ends_at')->nullable();
            $table->text('instructions')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('observation_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('observation_admission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->constrained()->restrictOnDelete();
            $table->foreignId('clinical_encounter_id')->nullable()->constrained()->nullOnDelete();
            $table->string('order_type')->index();
            $table->string('priority')->default('routine')->index();
            $table->longText('instructions');
            $table->foreignId('ordered_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('ordered_at')->index();
            $table->dateTime('scheduled_at')->nullable()->index();
            $table->string('status')->index();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('medication_administrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('observation_admission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->constrained()->restrictOnDelete();
            $table->foreignId('observation_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('prescription_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('medicine_id')->nullable()->constrained()->nullOnDelete();
            $table->string('medicine_name_snapshot');
            $table->string('dose');
            $table->string('route');
            $table->string('frequency')->nullable();
            $table->dateTime('scheduled_at')->index();
            $table->dateTime('administered_at')->nullable()->index();
            $table->foreignId('administered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('administration_status')->index();
            $table->text('omission_reason')->nullable();
            $table->text('refusal_reason')->nullable();
            $table->text('adverse_reaction')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('iv_fluid_administrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('observation_admission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->constrained()->restrictOnDelete();
            $table->foreignId('observation_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('prescription_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('medicine_id')->nullable()->constrained()->nullOnDelete();
            $table->string('fluid_name_snapshot');
            $table->unsignedInteger('volume_ml');
            $table->decimal('rate_ml_per_hour', 8, 2)->nullable();
            $table->unsignedInteger('drops_per_minute')->nullable();
            $table->string('route')->default('IV');
            $table->dateTime('started_at')->index();
            $table->dateTime('expected_end_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('started_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->index();
            $table->unsignedInteger('remaining_volume_ml')->nullable();
            $table->text('reaction_notes')->nullable();
            $table->string('cannula_site')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('oxygen_therapy_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('observation_admission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('observation_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('delivery_method')->index();
            $table->decimal('flow_rate_lpm', 6, 2)->nullable();
            $table->string('target_spo2')->nullable();
            $table->dateTime('started_at')->index();
            $table->dateTime('ended_at')->nullable();
            $table->foreignId('started_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('ended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->index();
            $table->decimal('pre_spo2', 5, 2)->nullable();
            $table->decimal('post_spo2', 5, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('nebulization_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('observation_admission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('observation_order_id')->nullable()->constrained()->nullOnDelete();
            $table->text('medication_details');
            $table->dateTime('started_at')->index();
            $table->dateTime('completed_at')->nullable();
            $table->decimal('pre_spo2', 5, 2)->nullable();
            $table->decimal('post_spo2', 5, 2)->nullable();
            $table->unsignedSmallInteger('pre_respiratory_rate')->nullable();
            $table->unsignedSmallInteger('post_respiratory_rate')->nullable();
            $table->foreignId('administered_by')->constrained('users')->restrictOnDelete();
            $table->text('response')->nullable();
            $table->text('adverse_reaction')->nullable();
            $table->string('status')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('bedside_procedures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('observation_admission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->constrained()->restrictOnDelete();
            $table->foreignId('observation_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('procedure_name_snapshot');
            $table->foreignId('performed_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('assisted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('performed_at')->index();
            $table->string('status')->index();
            $table->longText('findings')->nullable();
            $table->json('materials_used')->nullable();
            $table->text('complications')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('invoice_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('intake_output_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('observation_admission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('recorded_at')->index();
            $table->string('record_type')->index();
            $table->string('route_or_source')->nullable();
            $table->text('description')->nullable();
            $table->decimal('volume_ml', 10, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('nursing_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('observation_admission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('observation_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('task_type')->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('priority')->default('routine')->index();
            $table->dateTime('due_at')->nullable()->index();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->index();
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('nursing_handovers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('observation_admission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('from_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('shift_name')->nullable();
            $table->dateTime('handover_at')->index();
            $table->longText('patient_condition');
            $table->longText('pending_medications')->nullable();
            $table->longText('pending_orders')->nullable();
            $table->longText('iv_fluids_status')->nullable();
            $table->longText('critical_alerts')->nullable();
            $table->longText('special_instructions')->nullable();
            $table->dateTime('next_observation_due_at')->nullable();
            $table->text('referral_status')->nullable();
            $table->text('discharge_plan')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('acknowledged_at')->nullable();
            $table->timestamps();
        });

        Schema::create('observation_clinical_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('observation_admission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->constrained()->restrictOnDelete();
            $table->foreignId('reviewed_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('reviewed_at')->index();
            $table->longText('current_condition');
            $table->longText('examination_findings')->nullable();
            $table->longText('diagnosis_update')->nullable();
            $table->longText('treatment_plan')->nullable();
            $table->boolean('continue_observation')->default(true);
            $table->boolean('ready_for_discharge')->default(false);
            $table->boolean('referral_required')->default(false);
            $table->dateTime('next_review_at')->nullable()->index();
            $table->text('notes')->nullable();
            $table->string('status')->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('observation_discharges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('observation_admission_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->constrained()->restrictOnDelete();
            $table->string('discharge_type')->index();
            $table->string('discharge_condition')->index();
            $table->text('final_diagnosis')->nullable();
            $table->longText('treatment_summary')->nullable();
            $table->longText('procedures_summary')->nullable();
            $table->longText('medications_on_discharge')->nullable();
            $table->boolean('follow_up_required')->default(false);
            $table->date('follow_up_date')->nullable();
            $table->foreignId('follow_up_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->longText('discharge_instructions')->nullable();
            $table->longText('warning_signs')->nullable();
            $table->foreignId('discharged_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('authorized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('discharged_at')->index();
            $table->string('billing_status')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('observation_lama_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('observation_admission_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->text('lama_reason');
            $table->longText('counselling_notes');
            $table->longText('risks_explained')->nullable();
            $table->string('patient_or_guardian_name');
            $table->foreignId('witness_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('acknowledged_at');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('observation_absconded_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('observation_admission_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->dateTime('last_seen_at');
            $table->text('last_known_condition')->nullable();
            $table->foreignId('discovered_by')->constrained('users')->restrictOnDelete();
            $table->longText('actions_taken')->nullable();
            $table->boolean('guardian_contacted')->default(false);
            $table->boolean('management_notified')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('observation_death_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('observation_admission_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->constrained()->restrictOnDelete();
            $table->foreignId('declared_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('declared_at')->index();
            $table->text('suspected_cause')->nullable();
            $table->longText('circumstances')->nullable();
            $table->boolean('resuscitation_attempted')->default(false);
            $table->longText('resuscitation_notes')->nullable();
            $table->boolean('next_of_kin_notified')->default(false);
            $table->foreignId('notified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('notified_at')->nullable();
            $table->string('body_released_to')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('bed_cleaning_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('bed_id')->constrained()->restrictOnDelete();
            $table->foreignId('observation_admission_id')->nullable()->constrained()->nullOnDelete();
            $table->string('cleaning_type')->index();
            $table->string('status')->index();
            $table->dateTime('requested_at')->index();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('cleaned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'bed_cleaning_records',
            'observation_death_records',
            'observation_absconded_records',
            'observation_lama_records',
            'observation_discharges',
            'observation_clinical_reviews',
            'nursing_handovers',
            'nursing_tasks',
            'intake_output_records',
            'bedside_procedures',
            'nebulization_records',
            'oxygen_therapy_records',
            'iv_fluid_administrations',
            'medication_administrations',
            'observation_orders',
            'observation_schedules',
            'nursing_observations',
            'bed_reservations',
            'bed_assignments',
            'observation_admissions',
            'beds',
            'observation_rooms',
            'observation_admission_number_sequences',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
