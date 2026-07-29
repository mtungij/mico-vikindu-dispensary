<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laboratory_report_number_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();
            $table->unique(['facility_id', 'year'], 'lab_report_seq_facility_year_unq');
        });

        Schema::table('laboratory_orders', function (Blueprint $table): void {
            $table->string('report_number', 50)->nullable()->after('order_number');
            $table->unsignedInteger('report_revision')->default(1)->after('report_number');
            $table->timestamp('report_generated_at')->nullable()->after('report_revision');
            $table->unique(['facility_id', 'report_number'], 'lab_orders_report_number_unq');
        });
    }

    public function down(): void
    {
        Schema::table('laboratory_orders', function (Blueprint $table): void {
            $table->dropUnique('lab_orders_report_number_unq');
            $table->dropColumn(['report_number', 'report_revision', 'report_generated_at']);
        });

        Schema::dropIfExists('laboratory_report_number_sequences');
    }
};
