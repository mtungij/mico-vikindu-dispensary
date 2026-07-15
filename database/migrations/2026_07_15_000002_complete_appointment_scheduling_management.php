<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_number_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year')->nullable();
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();
            $table->unique(['facility_id', 'year']);
        });

        Schema::table('appointments', function (Blueprint $table): void {
            if (! Schema::hasColumn('appointments', 'appointment_number')) {
                $table->string('appointment_number', 50)->nullable()->after('visit_id');
            }
            if (! Schema::hasColumn('appointments', 'staff_id')) {
                $table->foreignId('staff_id')->nullable()->after('department_id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('appointments', 'service_id')) {
                $table->foreignId('service_id')->nullable()->after('staff_id')->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('appointments', 'appointment_date')) {
                $table->date('appointment_date')->nullable()->after('appointment_type');
            }
            if (! Schema::hasColumn('appointments', 'appointment_time')) {
                $table->time('appointment_time')->nullable()->after('appointment_date');
            }
            if (! Schema::hasColumn('appointments', 'estimated_duration')) {
                $table->unsignedInteger('estimated_duration')->default(30)->after('appointment_time');
            }
            if (! Schema::hasColumn('appointments', 'priority')) {
                $table->string('priority', 30)->default('normal')->after('estimated_duration');
            }
            if (! Schema::hasColumn('appointments', 'reminder_sms_sent')) {
                $table->boolean('reminder_sms_sent')->default(false)->after('reminder_status');
            }
            if (! Schema::hasColumn('appointments', 'reminder_whatsapp_sent')) {
                $table->boolean('reminder_whatsapp_sent')->default(false)->after('reminder_sms_sent');
            }
            if (! Schema::hasColumn('appointments', 'reminder_date')) {
                $table->dateTime('reminder_date')->nullable()->after('reminder_whatsapp_sent');
            }
            if (! Schema::hasColumn('appointments', 'booked_by')) {
                $table->foreignId('booked_by')->nullable()->after('reminder_date')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('appointments', 'checked_in_by')) {
                $table->foreignId('checked_in_by')->nullable()->after('booked_by')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('appointments', 'checked_in_at')) {
                $table->timestamp('checked_in_at')->nullable()->after('checked_in_by');
            }
            if (! Schema::hasColumn('appointments', 'cancelled_by')) {
                $table->foreignId('cancelled_by')->nullable()->after('checked_in_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('appointments', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('cancelled_by');
            }
            if (! Schema::hasColumn('appointments', 'cancellation_reason')) {
                $table->text('cancellation_reason')->nullable()->after('cancelled_at');
            }
            if (! Schema::hasColumn('appointments', 'rescheduled_from')) {
                $table->foreignId('rescheduled_from')->nullable()->after('cancellation_reason')->constrained('appointments')->nullOnDelete();
            }

            $table->unique(['facility_id', 'appointment_number'], 'appt_fac_no_uq');
            $table->index(['facility_id', 'appointment_date', 'status'], 'appt_fac_date_status_idx');
            $table->index(['facility_id', 'staff_id', 'appointment_date', 'appointment_time'], 'appt_fac_staff_slot_idx');
        });

        Schema::create('doctor_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('working_day', 20)->default('monday')->index();
            $table->json('working_days')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->time('break_start')->nullable();
            $table->time('break_end')->nullable();
            $table->unsignedInteger('slot_duration')->default(30);
            $table->unsignedInteger('max_patients_per_day')->nullable();
            $table->unsignedInteger('max_patients_per_hour')->nullable();
            $table->json('unavailable_dates')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['facility_id', 'staff_id', 'working_day'], 'doc_sched_fac_staff_day_uq');
        });

        Schema::create('department_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->string('working_day', 20)->default('monday')->index();
            $table->time('opening_time')->nullable();
            $table->time('closing_time')->nullable();
            $table->time('lunch_start')->nullable();
            $table->time('lunch_end')->nullable();
            $table->unsignedInteger('slot_duration')->default(30);
            $table->unsignedInteger('maximum_daily_capacity')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['facility_id', 'department_id', 'working_day'], 'dept_sched_fac_dept_day_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('department_schedules');
        Schema::dropIfExists('doctor_schedules');
        Schema::dropIfExists('appointment_number_sequences');

        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropUnique('appt_fac_no_uq');
            $table->dropIndex('appt_fac_date_status_idx');
            $table->dropIndex('appt_fac_staff_slot_idx');

            if (Schema::hasColumn('appointments', 'staff_id')) {
                $table->dropConstrainedForeignId('staff_id');
            }
            if (Schema::hasColumn('appointments', 'service_id')) {
                $table->dropConstrainedForeignId('service_id');
            }
            if (Schema::hasColumn('appointments', 'booked_by')) {
                $table->dropConstrainedForeignId('booked_by');
            }
            if (Schema::hasColumn('appointments', 'checked_in_by')) {
                $table->dropConstrainedForeignId('checked_in_by');
            }
            if (Schema::hasColumn('appointments', 'cancelled_by')) {
                $table->dropConstrainedForeignId('cancelled_by');
            }
            if (Schema::hasColumn('appointments', 'rescheduled_from')) {
                $table->dropConstrainedForeignId('rescheduled_from');
            }

            $table->dropColumn([
                'appointment_number',
                'appointment_date',
                'appointment_time',
                'estimated_duration',
                'priority',
                'reminder_sms_sent',
                'reminder_whatsapp_sent',
                'reminder_date',
                'checked_in_at',
                'cancelled_at',
                'cancellation_reason',
            ]);
        });
    }
};
