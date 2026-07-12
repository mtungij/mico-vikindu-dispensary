<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dental_endodontic_case_number_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year')->nullable();
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();
            $table->unique(['facility_id', 'year']);
        });

        Schema::create('dental_procedure_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 50);
            $table->string('category')->index();
            $table->text('description')->nullable();
            $table->boolean('requires_tooth')->default(false);
            $table->boolean('requires_surface')->default(false);
            $table->boolean('requires_consent')->default(false);
            $table->boolean('requires_payment')->default(true);
            $table->boolean('updates_odontogram')->default(true);
            $table->boolean('can_require_observation')->default(false);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'code']);
        });

        Schema::create('dental_anaesthetic_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 50);
            $table->string('generic_name')->nullable();
            $table->string('concentration')->nullable();
            $table->foreignId('medicine_id')->nullable()->constrained()->nullOnDelete();
            $table->string('route')->nullable();
            $table->text('maximum_dose_note')->nullable();
            $table->text('warnings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'code']);
        });

        Schema::create('dental_consent_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 50);
            $table->string('consent_type')->index();
            $table->longText('content');
            $table->longText('risks')->nullable();
            $table->longText('alternatives')->nullable();
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'code']);
        });

        Schema::create('dental_procedure_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 50);
            $table->foreignId('dental_procedure_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('default_diagnosis')->nullable();
            $table->string('default_anaesthesia_type')->nullable();
            $table->foreignId('default_anaesthetic_id')->nullable()->constrained('dental_anaesthetic_types')->nullOnDelete();
            $table->boolean('requires_consent')->default(false);
            $table->foreignId('consent_template_id')->nullable()->constrained('dental_consent_templates')->nullOnDelete();
            $table->json('default_materials')->nullable();
            $table->longText('default_post_op_instructions')->nullable();
            $table->unsignedInteger('default_follow_up_days')->nullable();
            $table->boolean('send_to_observation')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'code']);
        });

        Schema::create('dental_rooms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('code', 50);
            $table->string('location')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'code']);
        });

        Schema::create('dental_chairs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dental_room_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 50);
            $table->string('status')->default('available')->index();
            $table->foreignId('assigned_provider_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'code']);
        });

        Schema::create('dental_appointment_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 50);
            $table->unsignedInteger('default_duration_minutes')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'code']);
        });

        Schema::table('dental_encounters', function (Blueprint $table): void {
            $table->string('complaint_duration')->nullable()->after('complaint');
            $table->longText('medication_history')->nullable()->after('medical_history_review');
            $table->longText('allergy_history')->nullable()->after('medication_history');
        });

        Schema::table('dental_tooth_findings', function (Blueprint $table): void {
            $table->json('surfaces')->nullable()->after('surface');
        });

        Schema::table('dental_treatment_plans', function (Blueprint $table): void {
            $table->decimal('patient_estimated_amount', 15, 2)->default(0)->after('estimated_total');
            $table->decimal('insurance_estimated_amount', 15, 2)->default(0)->after('patient_estimated_amount');
            $table->dateTime('accepted_by_patient_at')->nullable()->after('approved_at');
            $table->dateTime('declined_at')->nullable()->after('accepted_by_patient_at');
            $table->text('cancellation_reason')->nullable()->after('declined_at');
        });

        Schema::table('dental_treatment_plan_items', function (Blueprint $table): void {
            $table->foreignId('dental_procedure_type_id')->nullable()->after('service_id')->constrained('dental_procedure_types')->nullOnDelete();
            $table->decimal('patient_amount', 15, 2)->default(0)->after('unit_price_snapshot');
            $table->decimal('insurance_amount', 15, 2)->default(0)->after('patient_amount');
            $table->string('phase_name')->nullable()->after('sequence_order');
        });

        Schema::table('dental_procedures', function (Blueprint $table): void {
            $table->foreignId('dental_procedure_type_id')->nullable()->after('service_id')->constrained('dental_procedure_types')->nullOnDelete();
            $table->boolean('observation_required')->default(false)->after('follow_up_date');
        });

        Schema::table('dental_consents', function (Blueprint $table): void {
            $table->foreignId('dental_consent_template_id')->nullable()->after('dental_procedure_id')->constrained('dental_consent_templates')->nullOnDelete();
        });

        Schema::table('dental_endodontic_cases', function (Blueprint $table): void {
            $table->string('case_number', 50)->nullable()->after('dental_encounter_id');
        });
    }

    public function down(): void
    {
        Schema::table('dental_endodontic_cases', fn (Blueprint $table) => $table->dropColumn('case_number'));
        Schema::table('dental_consents', fn (Blueprint $table) => $table->dropConstrainedForeignId('dental_consent_template_id'));
        Schema::table('dental_procedures', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('dental_procedure_type_id');
            $table->dropColumn('observation_required');
        });
        Schema::table('dental_treatment_plan_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('dental_procedure_type_id');
            $table->dropColumn(['patient_amount', 'insurance_amount', 'phase_name']);
        });
        Schema::table('dental_treatment_plans', fn (Blueprint $table) => $table->dropColumn(['patient_estimated_amount', 'insurance_estimated_amount', 'accepted_by_patient_at', 'declined_at', 'cancellation_reason']));
        Schema::table('dental_tooth_findings', fn (Blueprint $table) => $table->dropColumn('surfaces'));
        Schema::table('dental_encounters', fn (Blueprint $table) => $table->dropColumn(['complaint_duration', 'medication_history', 'allergy_history']));
        Schema::dropIfExists('dental_appointment_types');
        Schema::dropIfExists('dental_chairs');
        Schema::dropIfExists('dental_rooms');
        Schema::dropIfExists('dental_procedure_templates');
        Schema::dropIfExists('dental_consent_templates');
        Schema::dropIfExists('dental_anaesthetic_types');
        Schema::dropIfExists('dental_procedure_types');
        Schema::dropIfExists('dental_endodontic_case_number_sequences');
    }
};
