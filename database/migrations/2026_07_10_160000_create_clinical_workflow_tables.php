<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['clinical_encounter_number_sequences', 'laboratory_order_number_sequences', 'prescription_number_sequences', 'referral_number_sequences'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table): void {
                $table->id();
                $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
                $table->unsignedSmallInteger('year')->nullable();
                $table->unsignedBigInteger('last_number')->default(0);
                $table->timestamps();
                $table->unique(['facility_id', 'year']);
            });
        }

        Schema::create('triage_assessments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('queue_id')->nullable()->constrained('patient_queues')->nullOnDelete();
            $table->foreignId('assessed_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('assessed_at');
            $table->unsignedInteger('sequence_number')->default(1);
            $table->string('triage_level')->index();
            $table->text('chief_complaint_summary')->nullable();
            $table->decimal('temperature', 5, 2)->nullable();
            $table->unsignedSmallInteger('systolic_bp')->nullable();
            $table->unsignedSmallInteger('diastolic_bp')->nullable();
            $table->unsignedSmallInteger('pulse_rate')->nullable();
            $table->unsignedSmallInteger('respiratory_rate')->nullable();
            $table->decimal('oxygen_saturation', 5, 2)->nullable();
            $table->decimal('weight_kg', 8, 2)->nullable();
            $table->decimal('height_cm', 8, 2)->nullable();
            $table->decimal('bmi', 6, 2)->nullable();
            $table->decimal('blood_glucose', 8, 2)->nullable();
            $table->decimal('muac_cm', 6, 2)->nullable();
            $table->unsignedTinyInteger('pain_score')->nullable();
            $table->string('consciousness_level')->nullable();
            $table->string('pregnancy_status')->nullable();
            $table->unsignedTinyInteger('gestational_age_weeks')->nullable();
            $table->json('danger_signs')->nullable();
            $table->boolean('allergies_confirmed')->default(false);
            $table->string('fall_risk')->nullable();
            $table->string('infection_risk')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->index();
            $table->text('amendment_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['facility_id', 'visit_id', 'status']);
        });

        Schema::create('clinical_encounters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->string('encounter_type')->index();
            $table->string('encounter_number', 50);
            $table->foreignId('provider_user_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->string('status')->index();
            $table->text('chief_complaint')->nullable();
            $table->longText('history_of_presenting_illness')->nullable();
            $table->longText('past_medical_history')->nullable();
            $table->longText('surgical_history')->nullable();
            $table->longText('medication_history')->nullable();
            $table->longText('allergy_history')->nullable();
            $table->longText('family_history')->nullable();
            $table->longText('social_history')->nullable();
            $table->longText('obstetric_history')->nullable();
            $table->longText('gynecological_history')->nullable();
            $table->longText('review_of_systems')->nullable();
            $table->longText('physical_examination')->nullable();
            $table->longText('clinical_summary')->nullable();
            $table->longText('assessment_notes')->nullable();
            $table->longText('treatment_plan')->nullable();
            $table->longText('discharge_instructions')->nullable();
            $table->boolean('follow_up_required')->default(false);
            $table->date('follow_up_date')->nullable();
            $table->string('outcome')->nullable()->index();
            $table->foreignId('signed_off_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('signed_off_at')->nullable();
            $table->text('amendment_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'encounter_number']);
            $table->index(['facility_id', 'visit_id', 'provider_user_id', 'status']);
        });

        Schema::create('clinical_complaints', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinical_encounter_id')->constrained()->cascadeOnDelete();
            $table->string('complaint');
            $table->unsignedInteger('duration_value')->nullable();
            $table->string('duration_unit')->nullable();
            $table->string('severity')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('physical_examinations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinical_encounter_id')->constrained()->cascadeOnDelete();
            $table->string('examination_system');
            $table->longText('findings')->nullable();
            $table->string('status')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('icd10_codes', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('chapter')->nullable();
            $table->string('category')->nullable();
            $table->boolean('is_billable')->default(true);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['is_active', 'code']);
        });

        Schema::create('diagnoses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('clinical_encounter_id')->constrained()->cascadeOnDelete();
            $table->string('diagnosis_type')->index();
            $table->string('icd10_code', 20)->nullable()->index();
            $table->string('diagnosis_name');
            $table->text('description')->nullable();
            $table->string('certainty')->index();
            $table->boolean('is_primary')->default(false);
            $table->foreignId('diagnosed_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('diagnosed_at');
            $table->dateTime('resolved_at')->nullable();
            $table->string('status')->index();
            $table->text('error_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['facility_id', 'patient_id', 'diagnosed_at']);
        });

        Schema::create('laboratory_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('clinical_encounter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ordered_by')->constrained('users')->restrictOnDelete();
            $table->string('order_number', 50);
            $table->string('priority')->default('normal');
            $table->text('clinical_notes')->nullable();
            $table->text('provisional_diagnosis')->nullable();
            $table->string('status')->index();
            $table->dateTime('ordered_at');
            $table->string('payment_status')->index();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'order_number']);
        });

        Schema::create('laboratory_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('laboratory_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->string('test_name_snapshot');
            $table->string('test_code_snapshot')->nullable();
            $table->decimal('unit_price_snapshot', 15, 2);
            $table->decimal('payer_amount', 15, 2);
            $table->decimal('insurance_amount', 15, 2)->default(0);
            $table->decimal('patient_amount', 15, 2)->default(0);
            $table->string('priority')->default('normal');
            $table->string('status')->default('ordered');
            $table->string('specimen_type')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('invoice_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('prescriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('clinical_encounter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prescribed_by')->constrained('users')->restrictOnDelete();
            $table->string('prescription_number', 50);
            $table->string('status')->index();
            $table->text('notes')->nullable();
            $table->dateTime('prescribed_at');
            $table->dateTime('dispensed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'prescription_number']);
        });

        Schema::create('prescription_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('prescription_id')->constrained()->cascadeOnDelete();
            $table->string('medication_name');
            $table->string('generic_name')->nullable();
            $table->string('strength')->nullable();
            $table->string('dosage_form')->nullable();
            $table->string('dose');
            $table->string('route')->nullable();
            $table->string('frequency');
            $table->unsignedInteger('duration_value');
            $table->string('duration_unit');
            $table->decimal('quantity', 12, 2)->nullable();
            $table->text('instructions')->nullable();
            $table->string('indication')->nullable();
            $table->boolean('substitution_allowed')->default(true);
            $table->string('status')->default('prescribed');
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('clinical_procedure_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('clinical_encounter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ordered_by')->constrained('users')->restrictOnDelete();
            $table->string('procedure_name_snapshot');
            $table->text('instructions')->nullable();
            $table->string('priority')->default('normal');
            $table->string('status')->index();
            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('performed_at')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('invoice_item_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('appointments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('clinical_encounter_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('appointment_type')->index();
            $table->dateTime('scheduled_start')->index();
            $table->dateTime('scheduled_end')->nullable();
            $table->string('status')->index();
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->string('reminder_status')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['facility_id', 'assigned_to_user_id', 'scheduled_start']);
        });

        Schema::create('patient_referrals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('clinical_encounter_id')->nullable()->constrained()->nullOnDelete();
            $table->string('referral_number', 50);
            $table->string('referral_type')->index();
            $table->string('destination_facility_name');
            $table->string('destination_department')->nullable();
            $table->string('destination_contact')->nullable();
            $table->text('reason');
            $table->text('provisional_diagnosis')->nullable();
            $table->longText('clinical_summary')->nullable();
            $table->longText('treatment_given')->nullable();
            $table->longText('investigations_done')->nullable();
            $table->longText('current_medications')->nullable();
            $table->json('vital_signs_snapshot')->nullable();
            $table->string('urgency')->index();
            $table->string('transport_method')->nullable();
            $table->string('accompanying_person')->nullable();
            $table->foreignId('referred_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('referred_at');
            $table->string('status')->index();
            $table->dateTime('feedback_received_at')->nullable();
            $table->text('feedback_notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'referral_number']);
        });

        Schema::create('clinical_note_amendments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinical_encounter_id')->constrained()->cascadeOnDelete();
            $table->string('field_name');
            $table->longText('old_value')->nullable();
            $table->longText('new_value')->nullable();
            $table->text('reason');
            $table->foreignId('amended_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('amended_at');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('clinical_alerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('clinical_encounter_id')->nullable()->constrained()->nullOnDelete();
            $table->string('alert_type')->index();
            $table->string('severity')->index();
            $table->string('title');
            $table->text('message');
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('status')->default('active')->index();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('acknowledged_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['facility_id', 'patient_id', 'status']);
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_alerts');
        Schema::dropIfExists('clinical_note_amendments');
        Schema::dropIfExists('patient_referrals');
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('clinical_procedure_orders');
        Schema::dropIfExists('prescription_items');
        Schema::dropIfExists('prescriptions');
        Schema::dropIfExists('laboratory_order_items');
        Schema::dropIfExists('laboratory_orders');
        Schema::dropIfExists('diagnoses');
        Schema::dropIfExists('icd10_codes');
        Schema::dropIfExists('physical_examinations');
        Schema::dropIfExists('clinical_complaints');
        Schema::dropIfExists('clinical_encounters');
        Schema::dropIfExists('triage_assessments');
        Schema::dropIfExists('referral_number_sequences');
        Schema::dropIfExists('prescription_number_sequences');
        Schema::dropIfExists('laboratory_order_number_sequences');
        Schema::dropIfExists('clinical_encounter_number_sequences');
    }
};
