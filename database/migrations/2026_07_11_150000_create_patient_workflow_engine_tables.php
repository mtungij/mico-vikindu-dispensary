<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->json('value')->nullable();
            $table->string('type')->default('boolean');
            $table->string('group')->default('workflow');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['facility_id', 'key']);
        });

        Schema::create('department_queues', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->string('queue_prefix', 10);
            $table->boolean('is_active')->default(true);
            $table->boolean('print_tickets')->default(false);
            $table->boolean('display_screen_enabled')->default(false);
            $table->unsignedInteger('average_waiting_minutes')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['facility_id', 'department_id']);
        });

        Schema::table('visits', function (Blueprint $table): void {
            $table->foreignId('current_queue_id')->nullable()->after('current_department_id')->constrained('patient_queues')->nullOnDelete();
            $table->foreignId('current_assigned_user_id')->nullable()->after('current_queue_id')->constrained('users')->nullOnDelete();
        });

        Schema::table('patient_queues', function (Blueprint $table): void {
            $table->unsignedInteger('waiting_seconds')->nullable()->after('cancelled_at');
            $table->unsignedInteger('service_seconds')->nullable()->after('waiting_seconds');
            $table->timestamp('requeued_at')->nullable()->after('service_seconds');
        });

        Schema::table('visit_movements', function (Blueprint $table): void {
            $table->unsignedInteger('movement_duration_seconds')->nullable()->after('received_at');
            $table->boolean('emergency_override')->default(false)->after('movement_duration_seconds');
            $table->foreignId('authorized_by')->nullable()->after('emergency_override')->constrained('users')->nullOnDelete();
        });

        Schema::create('queue_tickets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_queue_id')->constrained('patient_queues')->cascadeOnDelete();
            $table->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->string('queue_number');
            $table->string('visit_number');
            $table->string('patient_name');
            $table->string('qr_payload')->nullable();
            $table->timestamp('printed_at')->nullable();
            $table->foreignId('printed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('queue_calls', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_queue_id')->constrained('patient_queues')->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->string('queue_number');
            $table->unsignedInteger('call_count')->default(1);
            $table->timestamp('called_at');
            $table->foreignId('called_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_calls');
        Schema::dropIfExists('queue_tickets');
        Schema::table('visit_movements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('authorized_by');
            $table->dropColumn(['movement_duration_seconds', 'emergency_override']);
        });
        Schema::table('patient_queues', function (Blueprint $table): void {
            $table->dropColumn(['waiting_seconds', 'service_seconds', 'requeued_at']);
        });
        Schema::table('visits', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('current_assigned_user_id');
            $table->dropConstrainedForeignId('current_queue_id');
        });
        Schema::dropIfExists('department_queues');
        Schema::dropIfExists('workflow_settings');
    }
};
