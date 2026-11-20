<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medication_administrations', function (Blueprint $table): void {
            $table->unsignedBigInteger('observation_admission_id')->nullable()->change();
            $table->decimal('quantity_administered', 12, 3)->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('medication_administrations')->whereNull('observation_admission_id')->exists()) {
            throw new RuntimeException('Outpatient medication administration records must be preserved.');
        }

        Schema::table('medication_administrations', function (Blueprint $table): void {
            $table->unsignedBigInteger('observation_admission_id')->nullable(false)->change();
            $table->dropColumn('quantity_administered');
        });
    }
};
