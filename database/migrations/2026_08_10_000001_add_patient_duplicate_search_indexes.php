<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table): void {
            $table->index(['facility_id', 'primary_phone'], 'patients_facility_phone_index');
        });
        Schema::table('patient_payer_profiles', function (Blueprint $table): void {
            $table->index(['facility_id', 'membership_number'], 'payer_profiles_facility_member_index');
        });

        $receptionistRoleId = DB::table('roles')->where('name', 'receptionist')->where('guard_name', 'web')->value('id');
        $overridePermissionIds = DB::table('permissions')
            ->whereIn('name', ['patients.override-duplicate-warning', 'reception.override-active-visit'])
            ->pluck('id');
        if ($receptionistRoleId && $overridePermissionIds->isNotEmpty()) {
            DB::table('role_has_permissions')
                ->where('role_id', $receptionistRoleId)
                ->whereIn('permission_id', $overridePermissionIds)
                ->delete();
        }
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table): void {
            $table->dropIndex('patients_facility_phone_index');
        });
        Schema::table('patient_payer_profiles', function (Blueprint $table): void {
            $table->dropIndex('payer_profiles_facility_member_index');
        });
    }
};
