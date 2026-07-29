<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table): void {
            $table->uuid('registration_idempotency_key')->nullable()->after('source');
            $table->unique(['facility_id', 'registration_idempotency_key'], 'visits_registration_idem_unq');
        });

        Schema::table('laboratory_orders', function (Blueprint $table): void {
            $table->dropForeign(['clinical_encounter_id']);
        });

        Schema::table('laboratory_orders', function (Blueprint $table): void {
            $table->foreignId('clinical_encounter_id')->nullable()->change();
            $table->string('source', 30)->default('opd')->after('clinical_encounter_id')->index();
            $table->foreign('clinical_encounter_id', 'lab_orders_encounter_fk')
                ->references('id')->on('clinical_encounters')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('laboratory_orders', function (Blueprint $table): void {
            $table->dropForeign('lab_orders_encounter_fk');
            $table->dropIndex(['source']);
            $table->dropColumn('source');
        });

        Schema::table('visits', function (Blueprint $table): void {
            $table->dropUnique('visits_registration_idem_unq');
            $table->dropColumn('registration_idempotency_key');
        });

        Schema::table('laboratory_orders', function (Blueprint $table): void {
            $table->foreignId('clinical_encounter_id')->nullable(false)->change();
            $table->foreign('clinical_encounter_id')
                ->references('id')->on('clinical_encounters')->cascadeOnDelete();
        });
    }
};
