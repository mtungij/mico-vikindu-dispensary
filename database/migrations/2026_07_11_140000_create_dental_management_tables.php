<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['dental_encounter_number_sequences', 'dental_plan_number_sequences', 'dental_procedure_number_sequences', 'orthodontic_case_number_sequences', 'dental_lab_order_number_sequences'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table): void {
                $table->id();
                $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
                $table->unsignedSmallInteger('year')->nullable();
                $table->unsignedBigInteger('last_number')->default(0);
                $table->timestamps();
                $table->unique(['facility_id', 'year']);
            });
        }

        Schema::create('dental_finding_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name', 120);
            $table->string('category')->index();
            $table->text('description')->nullable();
            $table->string('color', 20)->nullable();
            $table->string('icon', 80)->nullable();
            $table->boolean('applies_to_surface')->default(false);
            $table->boolean('applies_to_whole_tooth')->default(true);
            $table->boolean('severity_enabled')->default(false);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'code']);
        });

        Schema::create('dental_encounters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('clinical_encounter_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('provider_user_id')->constrained('users')->restrictOnDelete();
            $table->string('dental_encounter_number', 50);
            $table->text('complaint')->nullable();
            $table->longText('dental_history')->nullable();
            $table->longText('medical_history_review')->nullable();
            $table->longText('oral_hygiene_history')->nullable();
            $table->longText('previous_dental_treatment')->nullable();
            $table->string('tobacco_use')->nullable();
            $table->string('alcohol_use')->nullable();
            $table->string('brushing_frequency')->nullable();
            $table->string('flossing_frequency')->nullable();
            $table->string('dental_anxiety_level')->nullable();
            $table->string('pregnancy_status_snapshot')->nullable();
            $table->text('allergies_snapshot')->nullable();
            $table->text('current_medications_snapshot')->nullable();
            $table->longText('extraoral_examination')->nullable();
            $table->longText('intraoral_examination')->nullable();
            $table->longText('periodontal_summary')->nullable();
            $table->longText('occlusion_summary')->nullable();
            $table->longText('radiographic_findings')->nullable();
            $table->longText('clinical_summary')->nullable();
            $table->longText('treatment_plan_summary')->nullable();
            $table->string('status')->index();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('signed_off_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('signed_off_at')->nullable();
            $table->text('amendment_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'dental_encounter_number']);
            $table->index(['facility_id', 'patient_id', 'visit_id', 'status'], 'dent_enc_pat_visit_status_idx');
        });

        Schema::create('dental_tooth_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('dental_encounter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->string('tooth_number', 8);
            $table->string('dentition_type')->index();
            $table->string('tooth_status')->default('present')->index();
            $table->string('mobility_grade')->nullable();
            $table->string('eruption_status')->nullable();
            $table->decimal('periodontal_pocket_depth', 5, 2)->nullable();
            $table->decimal('gingival_recession', 5, 2)->nullable();
            $table->string('furcation_involvement')->nullable();
            $table->string('percussion_tenderness')->nullable();
            $table->string('vitality_status')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['dental_encounter_id', 'tooth_number']);
            $table->index(['facility_id', 'patient_id', 'tooth_number']);
        });

        Schema::create('dental_tooth_findings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('dental_tooth_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dental_encounter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('finding_type_id')->constrained('dental_finding_types')->restrictOnDelete();
            $table->string('surface')->nullable()->index();
            $table->string('severity')->nullable();
            $table->string('finding_status')->default('active')->index();
            $table->text('description')->nullable();
            $table->foreignId('diagnosed_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('diagnosed_at');
            $table->dateTime('resolved_at')->nullable();
            $table->foreignId('supersedes_finding_id')->nullable()->constrained('dental_tooth_findings')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['facility_id', 'dental_encounter_id', 'finding_type_id'], 'dent_find_fac_enc_type_idx');
        });

        Schema::create('dental_examinations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('dental_encounter_id')->constrained()->cascadeOnDelete();
            $table->string('examination_type')->index();
            $table->string('area')->index();
            $table->string('status')->nullable();
            $table->longText('findings')->nullable();
            $table->string('severity')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('recorded_at');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('periodontal_assessments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('dental_encounter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->date('assessment_date');
            $table->decimal('plaque_index', 6, 2)->nullable();
            $table->decimal('bleeding_index', 6, 2)->nullable();
            $table->decimal('calculus_index', 6, 2)->nullable();
            $table->string('oral_hygiene_status')->nullable();
            $table->string('gingival_status')->nullable();
            $table->string('periodontal_diagnosis')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['facility_id', 'patient_id', 'assessment_date'], 'perio_fac_patient_date_idx');
        });

        Schema::create('periodontal_measurements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('periodontal_assessment_id')->constrained()->cascadeOnDelete();
            $table->string('tooth_number', 8);
            $table->string('site');
            $table->decimal('pocket_depth_mm', 5, 2)->nullable();
            $table->decimal('recession_mm', 5, 2)->nullable();
            $table->boolean('bleeding_on_probing')->default(false);
            $table->boolean('suppuration')->default(false);
            $table->string('mobility_grade')->nullable();
            $table->string('furcation_grade')->nullable();
            $table->boolean('plaque_present')->default(false);
            $table->boolean('calculus_present')->default(false);
            $table->timestamps();
            $table->index(['tooth_number', 'site']);
        });

        Schema::create('dental_diagnoses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('dental_encounter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $table->string('tooth_number', 8)->nullable();
            $table->string('surface')->nullable();
            $table->string('diagnosis_type')->index();
            $table->string('diagnosis_name');
            $table->string('icd10_code', 20)->nullable();
            $table->string('certainty')->default('provisional');
            $table->boolean('is_primary')->default(false);
            $table->string('status')->default('active')->index();
            $table->foreignId('diagnosed_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('diagnosed_at');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['facility_id', 'patient_id', 'diagnosed_at'], 'dent_diag_pat_date_idx');
        });

        Schema::create('dental_treatment_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('dental_encounter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $table->string('plan_number', 50);
            $table->string('title');
            $table->longText('description')->nullable();
            $table->string('status')->default('draft')->index();
            $table->decimal('estimated_total', 15, 2)->nullable();
            $table->string('priority')->nullable();
            $table->date('planned_start_date')->nullable();
            $table->date('expected_completion_date')->nullable();
            $table->boolean('consent_required')->default(false);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'plan_number']);
        });

        Schema::create('dental_treatment_plan_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dental_treatment_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->string('tooth_number', 8)->nullable();
            $table->json('surfaces')->nullable();
            $table->string('description_snapshot');
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('unit_price_snapshot', 15, 2);
            $table->decimal('total_amount', 15, 2);
            $table->unsignedInteger('sequence_order')->default(0);
            $table->string('status')->default('planned')->index();
            $table->date('scheduled_date')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('invoice_item_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('dental_procedures', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('dental_encounter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('treatment_plan_item_id')->nullable()->constrained('dental_treatment_plan_items')->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('procedure_number', 50);
            $table->string('procedure_type')->index();
            $table->string('tooth_number', 8)->nullable();
            $table->json('surfaces')->nullable();
            $table->string('procedure_name_snapshot');
            $table->text('indication')->nullable();
            $table->text('diagnosis_snapshot')->nullable();
            $table->string('anaesthesia_type')->nullable();
            $table->string('anaesthetic_used')->nullable();
            $table->string('anaesthetic_quantity')->nullable();
            $table->foreignId('performed_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('assisted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->string('status')->default('planned')->index();
            $table->longText('findings')->nullable();
            $table->longText('technique_notes')->nullable();
            $table->longText('complications')->nullable();
            $table->longText('post_procedure_instructions')->nullable();
            $table->boolean('follow_up_required')->default(false);
            $table->date('follow_up_date')->nullable();
            $table->foreignId('invoice_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'procedure_number']);
            $table->index(['facility_id', 'patient_id', 'procedure_type', 'completed_at'], 'dent_proc_pat_type_comp_idx');
        });

        Schema::create('dental_materials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('code', 40);
            $table->string('category')->index();
            $table->string('unit', 40);
            $table->text('description')->nullable();
            $table->boolean('track_inventory')->default(false);
            $table->foreignId('medicine_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'code']);
        });

        Schema::create('dental_procedure_materials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dental_procedure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dental_material_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->string('unit_snapshot');
            $table->string('batch_number')->nullable();
            $table->foreignId('medicine_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('unit_cost_snapshot', 15, 2)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('orthodontic_cases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('dental_encounter_id')->constrained()->cascadeOnDelete();
            $table->string('case_number', 50);
            $table->text('chief_concern');
            $table->longText('diagnosis')->nullable();
            $table->string('malocclusion_class')->nullable();
            $table->longText('treatment_goal')->nullable();
            $table->string('appliance_type')->nullable();
            $table->date('treatment_start_date')->nullable();
            $table->unsignedInteger('expected_duration_months')->nullable();
            $table->string('status')->default('assessment')->index();
            $table->foreignId('assigned_dentist')->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'case_number']);
        });

        Schema::create('orthodontic_visits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('orthodontic_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dental_encounter_id')->nullable()->constrained()->nullOnDelete();
            $table->date('visit_date');
            $table->string('visit_type');
            $table->longText('procedure_done')->nullable();
            $table->string('appliance_status')->nullable();
            $table->string('wire_details')->nullable();
            $table->string('elastic_details')->nullable();
            $table->string('oral_hygiene_status')->nullable();
            $table->date('next_visit_date')->nullable();
            $table->foreignId('provider_user_id')->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('orthodontic_measurements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('orthodontic_case_id')->constrained()->cascadeOnDelete();
            $table->string('measurement_type');
            $table->string('value');
            $table->string('unit')->nullable();
            $table->dateTime('recorded_at');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('dental_oral_surgery_details', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dental_procedure_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('flap_raised')->default(false);
            $table->boolean('bone_removed')->default(false);
            $table->boolean('tooth_sectioned')->default(false);
            $table->boolean('socket_debrided')->default(false);
            $table->boolean('hemostasis_achieved')->default(false);
            $table->boolean('sutures_used')->default(false);
            $table->string('suture_material')->nullable();
            $table->unsignedInteger('number_of_sutures')->nullable();
            $table->boolean('specimen_sent')->default(false);
            $table->string('specimen_reference')->nullable();
            $table->text('complications')->nullable();
            $table->string('post_op_condition')->nullable();
            $table->timestamps();
        });

        Schema::create('dental_restorative_details', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dental_procedure_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('cavity_class')->nullable();
            $table->string('preparation_type')->nullable();
            $table->string('liner_used')->nullable();
            $table->string('base_used')->nullable();
            $table->string('restorative_material')->nullable();
            $table->string('shade')->nullable();
            $table->string('matrix_used')->nullable();
            $table->boolean('finishing_polishing_done')->default(false);
            $table->boolean('occlusion_checked')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('dental_endodontic_cases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->string('tooth_number', 8);
            $table->foreignId('dental_encounter_id')->constrained()->cascadeOnDelete();
            $table->string('diagnosis');
            $table->unsignedInteger('canals_expected')->nullable();
            $table->unsignedInteger('canals_found')->nullable();
            $table->json('working_length_details')->nullable();
            $table->string('instrumentation_method')->nullable();
            $table->string('irrigation_solution')->nullable();
            $table->string('intracanal_medicament')->nullable();
            $table->string('obturation_material')->nullable();
            $table->string('status')->default('planned')->index();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('provider_user_id')->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['facility_id', 'patient_id', 'tooth_number'], 'dent_endo_pat_tooth_idx');
        });

        Schema::create('dental_cosmetic_procedure_details', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dental_procedure_id')->unique()->constrained()->cascadeOnDelete();
            $table->longText('patient_expectations')->nullable();
            $table->string('baseline_shade')->nullable();
            $table->string('final_shade')->nullable();
            $table->string('product_or_material')->nullable();
            $table->unsignedInteger('sessions')->nullable();
            $table->text('sensitivity')->nullable();
            $table->longText('aftercare_instructions')->nullable();
            $table->timestamps();
        });

        Schema::create('dental_consents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dental_encounter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dental_procedure_id')->nullable()->constrained()->nullOnDelete();
            $table->string('consent_type')->index();
            $table->longText('consent_text_snapshot');
            $table->longText('risks_explained')->nullable();
            $table->longText('alternatives_explained')->nullable();
            $table->string('patient_or_guardian_name');
            $table->string('relationship_to_patient')->nullable();
            $table->boolean('consent_given');
            $table->dateTime('signed_at')->nullable();
            $table->string('patient_signature_path')->nullable();
            $table->foreignId('witness_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('clinician_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('dental_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('dental_encounter_id')->constrained()->cascadeOnDelete();
            $table->string('tooth_number', 8)->nullable();
            $table->string('attachment_type')->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('file_path');
            $table->string('mime_type');
            $table->unsignedBigInteger('file_size');
            $table->dateTime('captured_at')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['facility_id', 'patient_id', 'attachment_type'], 'dent_attach_pat_type_idx');
        });

        Schema::create('dental_lab_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dental_encounter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('treatment_plan_item_id')->nullable()->constrained('dental_treatment_plan_items')->nullOnDelete();
            $table->string('order_number', 50);
            $table->string('work_type')->index();
            $table->json('tooth_numbers')->nullable();
            $table->string('shade')->nullable();
            $table->string('material')->nullable();
            $table->longText('design_instructions')->nullable();
            $table->string('external_lab_name')->nullable();
            $table->string('external_reference')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('expected_at')->nullable();
            $table->dateTime('received_at')->nullable();
            $table->dateTime('fitted_at')->nullable();
            $table->string('status')->default('draft')->index();
            $table->foreignId('ordered_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'order_number']);
        });
    }

    public function down(): void
    {
        foreach ([
            'dental_lab_orders',
            'dental_attachments',
            'dental_consents',
            'dental_cosmetic_procedure_details',
            'dental_endodontic_cases',
            'dental_restorative_details',
            'dental_oral_surgery_details',
            'orthodontic_measurements',
            'orthodontic_visits',
            'orthodontic_cases',
            'dental_procedure_materials',
            'dental_materials',
            'dental_procedures',
            'dental_treatment_plan_items',
            'dental_treatment_plans',
            'dental_diagnoses',
            'periodontal_measurements',
            'periodontal_assessments',
            'dental_examinations',
            'dental_tooth_findings',
            'dental_tooth_records',
            'dental_encounters',
            'dental_finding_types',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        foreach (['dental_lab_order_number_sequences', 'orthodontic_case_number_sequences', 'dental_procedure_number_sequences', 'dental_plan_number_sequences', 'dental_encounter_number_sequences'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
