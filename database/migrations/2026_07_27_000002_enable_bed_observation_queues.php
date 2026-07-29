<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('departments')
            ->where('code', 'BED')
            ->update(['queue_enabled' => true, 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('departments')
            ->where('code', 'BED')
            ->update(['queue_enabled' => false, 'updated_at' => now()]);
    }
};
