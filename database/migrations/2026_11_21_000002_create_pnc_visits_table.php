<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pnc_visits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->constrained()->restrictOnDelete();
            $table->foreignId('rch_encounter_id')->unique()->constrained()->restrictOnDelete();
            $table->date('visit_date');
            $table->date('delivery_date')->nullable();
            $table->text('delivery_details')->nullable();
            $table->json('maternal_vitals');
            $table->text('maternal_observations')->nullable();
            $table->text('postpartum_symptoms')->nullable();
            $table->text('complications')->nullable();
            $table->text('breastfeeding_assessment')->nullable();
            $table->text('breastfeeding_counselling')->nullable();
            $table->text('newborn_condition')->nullable();
            $table->text('newborn_details')->nullable();
            $table->text('clinical_notes')->nullable();
            $table->text('treatment_plan')->nullable();
            $table->date('follow_up_date')->nullable();
            $table->string('status')->default('draft');
            $table->foreignId('provider_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['facility_id', 'patient_id', 'visit_date'], 'pnc_facility_patient_date_index');
        });
    }

    public function down(): void
    {
        if (DB::table('pnc_visits')->exists()) {
            throw new RuntimeException('Postnatal care visit records must be preserved.');
        }

        Schema::drop('pnc_visits');
    }
};
