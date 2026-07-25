<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinical_encounters', function (Blueprint $table): void {
            $table->foreignId('parent_encounter_id')
                ->nullable()
                ->after('department_id')
                ->constrained('clinical_encounters')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('clinical_encounters', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('parent_encounter_id');
        });
    }
};
