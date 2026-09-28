<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinical_encounters', function (Blueprint $table): void {
            $table->timestamp('partial_completed_at')->nullable();
            $table->foreignId('partial_completed_by')->nullable()->constrained('users')->nullOnDelete();
        });
        Schema::table('laboratory_order_items', function (Blueprint $table): void {
            $table->string('terminal_reason_code', 40)->nullable();
            $table->text('terminal_reason')->nullable();
            $table->timestamp('terminal_decided_at')->nullable();
            $table->foreignId('terminal_decided_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('laboratory_order_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('terminal_decided_by');
            $table->dropColumn(['terminal_reason_code', 'terminal_reason', 'terminal_decided_at']);
        });
        Schema::table('clinical_encounters', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('partial_completed_by');
            $table->dropColumn('partial_completed_at');
        });
    }
};
