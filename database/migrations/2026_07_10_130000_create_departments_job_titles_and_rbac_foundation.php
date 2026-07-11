<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->string('code', 20);
            $table->text('description')->nullable();
            $table->string('department_type')->nullable();
            $table->string('icon', 100)->nullable();
            $table->string('color', 20)->nullable();
            $table->string('phone_extension')->nullable();
            $table->string('location')->nullable();
            $table->boolean('queue_enabled')->default(false);
            $table->boolean('billing_enabled')->default(false);
            $table->boolean('clinical_department')->default(false);
            $table->boolean('stock_location_enabled')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'code']);
            $table->unique(['facility_id', 'name']);
        });

        Schema::create('job_titles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 100);
            $table->string('code', 20);
            $table->text('description')->nullable();
            $table->string('employment_category')->nullable();
            $table->boolean('requires_professional_license')->default(false);
            $table->string('license_authority')->nullable();
            $table->string('minimum_education_level')->nullable();
            $table->boolean('is_clinical')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['facility_id', 'code']);
            $table->unique(['facility_id', 'name']);
        });

        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'employee_number')) {
                $table->string('employee_number')->nullable()->after('phone');
            }
            if (! Schema::hasColumn('users', 'primary_department_id')) {
                $table->foreignId('primary_department_id')->nullable()->after('employee_number')->constrained('departments')->nullOnDelete();
            }
            if (! Schema::hasColumn('users', 'job_title_id')) {
                $table->foreignId('job_title_id')->nullable()->after('primary_department_id')->constrained('job_titles')->nullOnDelete();
            }
        });

        Schema::create('department_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->boolean('can_receive_queue')->default(false);
            $table->boolean('can_manage_department')->default(false);
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();
            $table->unique(['department_id', 'user_id']);
        });

        Schema::table('roles', function (Blueprint $table): void {
            $table->string('display_name')->nullable()->after('name');
            $table->text('description')->nullable()->after('display_name');
            $table->foreignId('facility_id')->nullable()->after('guard_name')->constrained()->cascadeOnDelete();
            $table->boolean('is_system')->default(false)->after('facility_id');
            $table->boolean('is_active')->default(true)->after('is_system');
            $table->foreignId('created_by')->nullable()->after('is_active')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->index('facility_id');
        });

        Schema::table('permissions', function (Blueprint $table): void {
            $table->string('label')->nullable()->after('name');
            $table->text('description')->nullable()->after('label');
            $table->string('module')->nullable()->after('description')->index();
        });
    }

    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table): void {
            $table->dropColumn(['label', 'description', 'module']);
        });

        Schema::table('roles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('facility_id');
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('updated_by');
            $table->dropColumn(['display_name', 'description', 'is_system', 'is_active']);
        });

        Schema::dropIfExists('department_user');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('job_title_id');
            $table->dropConstrainedForeignId('primary_department_id');
            $table->dropColumn('employee_number');
        });

        Schema::dropIfExists('job_titles');
        Schema::dropIfExists('departments');
    }
};
