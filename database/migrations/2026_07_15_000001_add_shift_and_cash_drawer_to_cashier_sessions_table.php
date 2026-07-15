<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cashier_sessions', function (Blueprint $table): void {
            if (! Schema::hasColumn('cashier_sessions', 'shift')) {
                $table->string('shift', 40)->default('morning')->after('session_number')->index();
            }

            if (! Schema::hasColumn('cashier_sessions', 'cash_drawer')) {
                $table->string('cash_drawer')->nullable()->after('opening_float');
            }

            $table->index(['facility_id', 'opened_at'], 'cash_sess_fac_opened_idx');
        });
    }

    public function down(): void
    {
        Schema::table('cashier_sessions', function (Blueprint $table): void {
            $table->dropIndex('cash_sess_fac_opened_idx');

            if (Schema::hasColumn('cashier_sessions', 'cash_drawer')) {
                $table->dropColumn('cash_drawer');
            }

            if (Schema::hasColumn('cashier_sessions', 'shift')) {
                $table->dropColumn('shift');
            }
        });
    }
};
