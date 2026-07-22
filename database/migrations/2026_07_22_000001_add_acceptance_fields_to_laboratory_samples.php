<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('laboratory_samples', function (Blueprint $table): void {
            $table->foreignId('accepted_by')->nullable()->after('received_at')->constrained('users')->nullOnDelete();
            $table->dateTime('accepted_at')->nullable()->after('accepted_by');
        });
    }

    public function down(): void
    {
        Schema::table('laboratory_samples', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('accepted_by');
            $table->dropColumn('accepted_at');
        });
    }
};
