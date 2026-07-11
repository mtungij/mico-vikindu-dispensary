<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laboratory_test_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('code', 40);
            $table->text('description')->nullable();
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

        Schema::create('specimen_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('code', 40);
            $table->text('description')->nullable();
            $table->string('container_type')->nullable();
            $table->text('collection_instructions')->nullable();
            $table->decimal('minimum_volume', 8, 2)->nullable();
            $table->string('volume_unit')->nullable();
            $table->string('storage_temperature')->nullable();
            $table->text('transport_instructions')->nullable();
            $table->text('rejection_criteria')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'code']);
            $table->unique(['facility_id', 'name']);
        });

        Schema::create('laboratory_tests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('laboratory_test_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('specimen_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('code', 60);
            $table->string('short_name')->nullable();
            $table->text('description')->nullable();
            $table->string('methodology')->nullable();
            $table->string('result_type')->index();
            $table->string('unit')->nullable();
            $table->string('default_reference_range')->nullable();
            $table->unsignedTinyInteger('decimal_places')->nullable();
            $table->unsignedInteger('turnaround_time_minutes')->nullable();
            $table->boolean('requires_fasting')->default(false);
            $table->boolean('requires_special_consent')->default(false);
            $table->boolean('allows_multiple_specimens')->default(false);
            $table->boolean('is_panel')->default(false);
            $table->boolean('is_outsourced')->default(false);
            $table->string('outsourced_provider')->nullable();
            $table->decimal('critical_low', 15, 4)->nullable();
            $table->decimal('critical_high', 15, 4)->nullable();
            $table->boolean('reportable')->default(true);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'code']);
            $table->index(
    ['facility_id', 'laboratory_test_category_id', 'is_active'],
    'lab_test_cat_active_idx'
);
        });

        Schema::create('laboratory_test_parameters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('laboratory_test_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_parameter_id')->nullable()->constrained('laboratory_test_parameters')->nullOnDelete();
            $table->string('name');
            $table->string('code', 60);
            $table->text('description')->nullable();
            $table->string('result_type');
            $table->string('unit')->nullable();
            $table->unsignedTinyInteger('decimal_places')->nullable();
            $table->string('default_reference_range')->nullable();
            $table->decimal('critical_low', 15, 4)->nullable();
            $table->decimal('critical_high', 15, 4)->nullable();
            $table->json('allowed_values')->nullable();
            $table->string('normal_value')->nullable();
            $table->boolean('is_required')->default(true);
            $table->boolean('is_heading')->default(false);
            $table->boolean('show_on_report')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['laboratory_test_id', 'code']);
            $table->index(['facility_id', 'laboratory_test_id']);
        });

        Schema::create('laboratory_test_panels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('laboratory_test_id')->constrained()->cascadeOnDelete();
            $table->foreignId('child_laboratory_test_id')->constrained('laboratory_tests')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_required')->default(true);
            $table->timestamps();
            $table->unique(
    ['laboratory_test_id', 'child_laboratory_test_id'],
    'lab_panel_child_unq'
);
        });

        Schema::create('laboratory_reference_ranges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('laboratory_test_id')->constrained()->cascadeOnDelete();
            $table->foreignId('laboratory_test_parameter_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('gender')->nullable();
            $table->unsignedInteger('minimum_age_days')->nullable();
            $table->unsignedInteger('maximum_age_days')->nullable();
            $table->string('pregnancy_status')->nullable();
            $table->decimal('lower_limit', 15, 4)->nullable();
            $table->decimal('upper_limit', 15, 4)->nullable();
            $table->string('textual_range')->nullable();
            $table->string('unit')->nullable();
            $table->string('interpretation')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('priority')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['facility_id', 'laboratory_test_id', 'is_active']);
        });

        Schema::table('laboratory_order_items', function (Blueprint $table): void {
            $table->foreignId('laboratory_test_id')->nullable()->after('service_id')->constrained()->nullOnDelete();
            $table->foreignId('specimen_type_id')->nullable()->after('laboratory_test_id')->constrained()->nullOnDelete();
            $table->string('result_status')->nullable()->after('status')->index();
            $table->timestamp('result_entered_at')->nullable()->after('result_status');
            $table->timestamp('result_verified_at')->nullable()->after('result_entered_at');
            $table->timestamp('result_released_at')->nullable()->after('result_verified_at');
        });

        Schema::create('laboratory_sample_rejection_reasons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->string('code', 40);
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('requires_recollection')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'code']);
        });

        Schema::create('laboratory_sample_number_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year')->nullable();
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();
            $table->unique(['facility_id', 'year']);
        });

        Schema::create('laboratory_samples', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('laboratory_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $table->string('sample_number', 60);
            $table->string('barcode_value')->nullable();
            $table->foreignId('specimen_type_id')->constrained()->restrictOnDelete();
            $table->string('container_type')->nullable();
            $table->foreignId('collected_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('collected_at');
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('received_at')->nullable();
            $table->string('collection_location')->nullable();
            $table->decimal('volume_collected', 8, 2)->nullable();
            $table->string('volume_unit')->nullable();
            $table->text('collection_notes')->nullable();
            $table->string('sample_status')->index();
            $table->string('quality_status')->nullable();
            $table->foreignId('rejection_reason_id')->nullable()->constrained('laboratory_sample_rejection_reasons')->nullOnDelete();
            $table->text('rejection_notes')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('rejected_at')->nullable();
            $table->foreignId('disposed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('disposed_at')->nullable();
            $table->dateTime('expiry_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'sample_number']);
            $table->index(['facility_id', 'sample_status']);
        });

        Schema::table('laboratory_order_items', function (Blueprint $table): void {
            $table->foreignId('sample_id')->nullable()->after('specimen_type_id')->constrained('laboratory_samples')->nullOnDelete();
        });

        Schema::create('laboratory_sample_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('laboratory_sample_id')->constrained()->cascadeOnDelete();
            $table->foreignId('laboratory_order_item_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('attached');
            $table->timestamps();
            $table->unique(['laboratory_sample_id', 'laboratory_order_item_id']);
        });

        Schema::create('laboratory_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('laboratory_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('laboratory_order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('laboratory_sample_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('laboratory_test_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('result_version')->default(1);
            $table->string('result_status')->index();
            $table->longText('overall_result')->nullable();
            $table->longText('interpretation')->nullable();
            $table->longText('comments')->nullable();
            $table->string('abnormal_flag')->nullable()->index();
            $table->string('reference_range_snapshot')->nullable();
            $table->string('methodology_snapshot')->nullable();
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('entered_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('verified_at')->nullable();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('released_at')->nullable();
            $table->foreignId('reviewed_by_clinician')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->text('amendment_reason')->nullable();
            $table->foreignId('supersedes_result_id')->nullable()->constrained('laboratory_results')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(
    ['facility_id', 'laboratory_order_item_id', 'result_status'],
    'lab_result_status_idx'
);
        });

        Schema::create('laboratory_result_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('laboratory_result_id')->constrained()->cascadeOnDelete();
            $table->foreignId('laboratory_test_parameter_id')->nullable()->constrained()->nullOnDelete();
            $table->string('parameter_name_snapshot');
            $table->string('parameter_code_snapshot')->nullable();
            $table->string('result_type');
            $table->decimal('numeric_value', 20, 6)->nullable();
            $table->longText('text_value')->nullable();
            $table->string('selected_value')->nullable();
            $table->boolean('boolean_value')->nullable();
            $table->string('unit_snapshot')->nullable();
            $table->string('reference_range_snapshot')->nullable();
            $table->decimal('lower_limit_snapshot', 15, 4)->nullable();
            $table->decimal('upper_limit_snapshot', 15, 4)->nullable();
            $table->string('abnormal_flag')->nullable()->index();
            $table->boolean('is_critical')->default(false);
            $table->text('comments')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

       Schema::create('laboratory_critical_result_notifications', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('facility_id')->constrained()->restrictOnDelete();

    $table->foreignId('laboratory_result_id');
    $table->foreign(
        'laboratory_result_id',
        'lab_critical_result_fk'
    )->references('id')
      ->on('laboratory_results')
      ->cascadeOnDelete();

    $table->foreignId('laboratory_result_value_id')->nullable();
    $table->foreign(
        'laboratory_result_value_id',
        'lab_critical_value_fk'
    )->references('id')
      ->on('laboratory_result_values')
      ->nullOnDelete();

    $table->foreignId('notified_to_user_id')->nullable();
    $table->foreign(
        'notified_to_user_id',
        'lab_critical_notified_user_fk'
    )->references('id')
      ->on('users')
      ->nullOnDelete();

    $table->string('notification_method');

    $table->foreignId('notified_by');
    $table->foreign(
        'notified_by',
        'lab_critical_notified_by_fk'
    )->references('id')
      ->on('users')
      ->restrictOnDelete();

    $table->dateTime('notified_at');

    $table->foreignId('acknowledged_by')->nullable();
    $table->foreign(
        'acknowledged_by',
        'lab_critical_ack_by_fk'
    )->references('id')
      ->on('users')
      ->nullOnDelete();

    $table->dateTime('acknowledged_at')->nullable();
    $table->text('communication_notes')->nullable();
    $table->string('status')->index();
    $table->timestamps();
});

        Schema::create('outsourced_laboratory_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('laboratory_order_item_id')->constrained()->cascadeOnDelete();
            $table->string('external_provider_name');
            $table->string('external_reference_number')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('expected_at')->nullable();
            $table->dateTime('received_at')->nullable();
            $table->string('status')->index();
            $table->string('result_document_path')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outsourced_laboratory_requests');
        Schema::dropIfExists('laboratory_critical_result_notifications');
        Schema::dropIfExists('laboratory_result_values');
        Schema::dropIfExists('laboratory_results');
        Schema::dropIfExists('laboratory_sample_items');
        Schema::table('laboratory_order_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('laboratory_test_id');
            $table->dropConstrainedForeignId('specimen_type_id');
            $table->dropConstrainedForeignId('sample_id');
            $table->dropColumn(['result_status', 'result_entered_at', 'result_verified_at', 'result_released_at']);
        });
        Schema::dropIfExists('laboratory_samples');
        Schema::dropIfExists('laboratory_sample_number_sequences');
        Schema::dropIfExists('laboratory_sample_rejection_reasons');
        Schema::dropIfExists('laboratory_reference_ranges');
        Schema::dropIfExists('laboratory_test_panels');
        Schema::dropIfExists('laboratory_test_parameters');
        Schema::dropIfExists('laboratory_tests');
        Schema::dropIfExists('specimen_types');
        Schema::dropIfExists('laboratory_test_categories');
    }
};
