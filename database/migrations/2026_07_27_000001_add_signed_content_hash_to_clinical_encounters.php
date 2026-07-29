<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinical_encounters', function (Blueprint $table): void {
            $table->string('signed_content_hash', 64)->nullable()->after('signed_off_at');
        });
    }

    public function down(): void
    {
        Schema::table('clinical_encounters', function (Blueprint $table): void {
            $table->dropColumn('signed_content_hash');
        });
    }
};
