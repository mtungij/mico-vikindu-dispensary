<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\DepartmentQueue;
use App\Models\Facility;
use App\Models\User;
use App\Models\WorkflowSetting;
use Illuminate\Database\Seeder;

class WorkflowSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $facility = Facility::query()->first();
        $admin = User::query()->where('is_super_admin', true)->first();
        if (! $facility) return;

        foreach ($this->settings() as $key => $value) {
            WorkflowSetting::query()->updateOrCreate(
                ['facility_id' => $facility->id, 'key' => $key],
                ['value' => $value, 'type' => 'boolean', 'group' => 'workflow', 'updated_by' => $admin?->id],
            );
        }

        $prefixes = ['REC' => 'RCP', 'BIL' => 'BIL', 'TRI' => 'TRI', 'OPD' => 'OPD', 'LAB' => 'LAB', 'PHA' => 'PHA', 'DEN' => 'DEN', 'RCH' => 'RCH', 'BED' => 'BED'];
        Department::query()->where('facility_id', $facility->id)->whereIn('code', array_keys($prefixes))->get()->each(function (Department $department) use ($facility, $admin, $prefixes): void {
            DepartmentQueue::query()->updateOrCreate(
                ['facility_id' => $facility->id, 'department_id' => $department->id],
                ['queue_prefix' => $prefixes[$department->code] ?? str($department->code)->upper()->substr(0, 3), 'is_active' => true, 'print_tickets' => false, 'display_screen_enabled' => false, 'created_by' => $admin?->id, 'updated_by' => $admin?->id],
            );
        });
    }

    private function settings(): array
    {
        return [
            'payment_before_consultation' => true,
            'use_triage_before_opd' => true,
            'allow_emergency_override' => true,
            'allow_insurance_skip_billing' => true,
            'require_payment_before_laboratory' => true,
            'require_payment_before_pharmacy' => true,
            'require_payment_before_bed_rest' => true,
            'require_doctor_review_after_laboratory' => true,
            'auto_complete_visit_after_dispensing' => false,
            'enable_department_queues' => true,
            'print_queue_tickets' => false,
            'enable_queue_display_screen' => false,
            'enable_queue_sms_notifications' => false,
        ];
    }
}
