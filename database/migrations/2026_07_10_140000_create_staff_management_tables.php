<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'password_changed_at')) {
                $table->timestamp('password_changed_at')->nullable()->after('last_login_ip');
            }
            if (! Schema::hasColumn('users', 'must_change_password')) {
                $table->boolean('must_change_password')->default(false)->after('password_changed_at');
            }
            if (! Schema::hasColumn('users', 'avatar_path')) {
                $table->string('avatar_path')->nullable()->after('must_change_password');
            }
            if (! Schema::hasColumn('users', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::create('staff_number_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year')->nullable();
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
            $table->unique(['facility_id', 'year']);
        });

        Schema::create('staff_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->string('employee_number', 40);
            $table->string('first_name', 50);
            $table->string('middle_name', 50)->nullable();
            $table->string('last_name', 50);
            $table->string('gender')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('marital_status')->nullable();
            $table->string('nationality')->default('Tanzanian');
            $table->string('nida_number', 40)->nullable();
            $table->string('passport_number', 40)->nullable();
            $table->string('primary_phone', 30);
            $table->string('secondary_phone', 30)->nullable();
            $table->string('personal_email', 150)->nullable();
            $table->text('physical_address')->nullable();
            $table->text('postal_address')->nullable();
            $table->string('region')->nullable();
            $table->string('district')->nullable();
            $table->string('ward')->nullable();
            $table->string('street_or_village')->nullable();
            $table->string('passport_photo_path')->nullable();
            $table->string('signature_path')->nullable();
            $table->text('biography')->nullable();
            $table->text('emergency_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'employee_number']);
            $table->unique(['facility_id', 'nida_number']);
            $table->unique(['facility_id', 'passport_number']);
            $table->index(['facility_id', 'primary_phone']);
        });

        Schema::create('employment_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('staff_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('job_title_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('primary_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('employment_category')->nullable();
            $table->string('employment_status')->default('active')->index();
            $table->date('employment_start_date')->nullable();
            $table->date('probation_end_date')->nullable();
            $table->date('contract_start_date')->nullable();
            $table->date('contract_end_date')->nullable();
            $table->date('termination_date')->nullable();
            $table->text('termination_reason')->nullable();
            $table->string('payroll_number')->nullable();
            $table->foreignId('supervisor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('work_location')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['facility_id', 'employment_status']);
        });

        Schema::create('staff_education_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('staff_profile_id')->constrained()->cascadeOnDelete();
            $table->string('education_level');
            $table->string('course_name');
            $table->string('institution_name');
            $table->string('country')->nullable();
            $table->unsignedSmallInteger('start_year')->nullable();
            $table->unsignedSmallInteger('graduation_year')->nullable();
            $table->string('certificate_number')->nullable();
            $table->string('grade_or_class')->nullable();
            $table->text('description')->nullable();
            $table->string('certificate_document_path')->nullable();
            $table->boolean('is_highest_qualification')->default(false);
            $table->string('verification_status')->default('pending');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('staff_professional_licenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('staff_profile_id')->constrained()->cascadeOnDelete();
            $table->string('license_type');
            $table->string('professional_body');
            $table->string('registration_number');
            $table->string('license_number')->nullable();
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable()->index();
            $table->string('status')->default('unknown')->index();
            $table->string('document_path')->nullable();
            $table->text('notes')->nullable();
            $table->string('verification_status')->default('pending');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('staff_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('staff_profile_id')->constrained()->cascadeOnDelete();
            $table->string('document_type');
            $table->string('document_name');
            $table->string('document_number')->nullable();
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable()->index();
            $table->string('file_path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('verification_status')->default('pending');
            $table->text('notes')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('staff_emergency_contacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('staff_profile_id')->constrained()->cascadeOnDelete();
            $table->string('full_name');
            $table->string('relationship');
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

        Schema::create('user_login_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('facility_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email_attempted')->nullable();
            $table->string('status')->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('device_name')->nullable();
            $table->string('browser')->nullable();
            $table->string('platform')->nullable();
            $table->timestamp('logged_in_at')->nullable();
            $table->timestamp('logged_out_at')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_login_histories');
        Schema::dropIfExists('staff_emergency_contacts');
        Schema::dropIfExists('staff_documents');
        Schema::dropIfExists('staff_professional_licenses');
        Schema::dropIfExists('staff_education_records');
        Schema::dropIfExists('employment_records');
        Schema::dropIfExists('staff_profiles');
        Schema::dropIfExists('staff_number_sequences');

        Schema::table('users', function (Blueprint $table): void {
            foreach (['password_changed_at', 'must_change_password', 'avatar_path', 'deleted_at'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
