<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('triage_assessments', function (Blueprint $table): void {
            $table->foreignId('completed_by')->nullable()->after('assessed_at')->constrained('users')->nullOnDelete();
            $table->dateTime('completed_at')->nullable()->after('completed_by');
        });
    }

    public function down(): void
    {
        Schema::table('triage_assessments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('completed_by');
            $table->dropColumn('completed_at');
        });
    }
};
