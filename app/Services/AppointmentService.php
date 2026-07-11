<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\ClinicalEncounter;
use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AppointmentService
{
    public function createFollowUp(ClinicalEncounter $encounter, array $data, $actor): Appointment
    {
        return DB::transaction(function () use ($encounter, $data, $actor) {
            $start = now()->parse($data['scheduled_start']);
            if ($start->isPast()) {
                throw ValidationException::withMessages(['scheduled_start' => 'Tarehe ya follow-up haiwezi kuwa ya nyuma.']);
            }
            $department = Department::query()->where('facility_id', $encounter->facility_id)->findOrFail($data['department_id'] ?? $encounter->department_id);
            if ($assigned = ($data['assigned_to_user_id'] ?? null)) {
                User::query()->whereHas('staffProfile', fn ($query) => $query->where('facility_id', $encounter->facility_id))->findOrFail($assigned);
                $overlap = Appointment::query()->where('facility_id', $encounter->facility_id)->where('assigned_to_user_id', $assigned)->where('status', '!=', AppointmentStatus::Cancelled->value)->where('scheduled_start', '<', $data['scheduled_end'] ?? $start->copy()->addMinutes(30))->where(fn ($q) => $q->whereNull('scheduled_end')->orWhere('scheduled_end', '>', $start))->exists();
                if ($overlap) {
                    throw ValidationException::withMessages(['assigned_to_user_id' => 'Mtoa huduma ana appointment inayogongana muda huo.']);
                }
            }

            $appointment = Appointment::query()->create([
                'facility_id' => $encounter->facility_id,
                'patient_id' => $encounter->patient_id,
                'visit_id' => $encounter->visit_id,
                'clinical_encounter_id' => $encounter->id,
                'department_id' => $department->id,
                'assigned_to_user_id' => $data['assigned_to_user_id'] ?? null,
                'appointment_type' => $data['appointment_type'] ?? AppointmentType::OpdFollowUp,
                'scheduled_start' => $start,
                'scheduled_end' => $data['scheduled_end'] ?? $start->copy()->addMinutes(30),
                'status' => AppointmentStatus::Scheduled,
                'reason' => $data['reason'] ?? 'OPD follow-up',
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);
            ActivityLog::query()->create(['user_id' => $actor->id, 'event' => 'appointment_created', 'subject_type' => $appointment::class, 'subject_id' => $appointment->id]);
            return $appointment;
        });
    }
}
