<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_super_admin')->default(false)->after('status');
        });

        Schema::create('facilities', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            $table->string('code', 30)->nullable()->unique();
            $table->string('facility_type');
            $table->string('ownership_type');
            $table->string('registration_number', 100)->nullable();
            $table->string('operating_license_number')->nullable();
            $table->date('operating_license_expiry_date')->nullable();
            $table->string('nhif_accreditation_number')->nullable();
            $table->string('nhif_contract_number')->nullable();
            $table->string('tin_number', 50)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('phone_primary');
            $table->string('phone_secondary')->nullable();
            $table->string('website')->nullable();
            $table->text('postal_address')->nullable();
            $table->text('physical_address')->nullable();
            $table->string('region');
            $table->string('district');
            $table->string('council')->nullable();
            $table->string('ward')->nullable();
            $table->string('street_or_village')->nullable();
            $table->string('country')->default('Tanzania');
            $table->string('timezone')->default('Africa/Dar_es_Salaam');
            $table->string('currency', 10)->default('TZS');
            $table->string('currency_symbol', 20)->default('TSh');
            $table->string('date_format', 30)->default('d/m/Y');
            $table->string('time_format', 30)->default('H:i');
            $table->string('logo_path')->nullable();
            $table->string('favicon_path')->nullable();
            $table->string('official_stamp_path')->nullable();
            $table->text('receipt_header')->nullable();
            $table->text('receipt_footer')->nullable();
            $table->text('report_footer')->nullable();
            $table->string('primary_color', 20)->default('#0F766E');
            $table->string('secondary_color', 20)->default('#14B8A6');
            $table->string('default_language', 10)->default('sw');
            $table->string('fallback_language', 10)->default('en');
            $table->unsignedTinyInteger('setup_current_step')->default(1);
            $table->timestamp('setup_completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('facility_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->longText('value')->nullable();
            $table->string('type')->default('string');
            $table->string('group')->nullable();
            $table->boolean('is_public')->default(false);
            $table->timestamps();
            $table->unique(['facility_id', 'key']);
        });

        Schema::create('facility_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->string('document_type');
            $table->string('document_name');
            $table->string('document_number')->nullable();
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('file_path');
            $table->string('verification_status')->default('pending');
            $table->text('notes')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('activity_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('facility_documents');
        Schema::dropIfExists('facility_settings');
        Schema::dropIfExists('facilities');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('is_super_admin');
        });
    }
};
