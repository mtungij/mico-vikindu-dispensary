<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table): void {
            $table->boolean('can_receive_patients')->default(true)->after('stock_location_enabled');
            $table->boolean('requires_consultation')->default(false)->after('can_receive_patients');
            $table->boolean('requires_triage')->default(false)->after('requires_consultation');
        });

        DB::table('departments')
            ->whereIn('code', ['ACC', 'ADM', 'STR', 'BIL', 'REC'])
            ->update(['can_receive_patients' => false]);

        DB::table('departments')
            ->whereIn('code', ['OPD', 'DEN', 'RCH', 'BED'])
            ->update(['requires_consultation' => true]);

        DB::table('departments')
            ->where('clinical_department', true)
            ->update(['requires_triage' => true]);
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table): void {
            $table->dropColumn(['can_receive_patients', 'requires_consultation', 'requires_triage']);
        });
    }
};
