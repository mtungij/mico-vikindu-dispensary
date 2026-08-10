<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table): void {
            $table->unique(
                ['invoice_id', 'reference_type', 'reference_id', 'service_id'],
                'inv_rx_charge_ref_uq'
            );
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->string('idempotency_key', 64)->nullable()->after('transaction_reference');
            $table->unique(['facility_id', 'idempotency_key'], 'pay_fac_idem_uq');
            $table->unique(
                ['facility_id', 'payment_method_id', 'transaction_reference'],
                'pay_fac_method_ref_uq'
            );
        });

        Schema::table('prescription_items', function (Blueprint $table): void {
            $table->string('terminal_status')->nullable()->after('status')->index();
            $table->text('terminal_reason')->nullable()->after('terminal_status');
            $table->timestamp('terminal_at')->nullable()->after('terminal_reason');
            $table->foreignId('terminal_by')->nullable()->after('terminal_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('prescription_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('terminal_by');
            $table->dropColumn(['terminal_status', 'terminal_reason', 'terminal_at']);
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique('pay_fac_idem_uq');
            $table->dropUnique('pay_fac_method_ref_uq');
            $table->dropColumn('idempotency_key');
        });

        Schema::table('invoice_items', function (Blueprint $table): void {
            $table->dropUnique('inv_rx_charge_ref_uq');
        });
    }
};
