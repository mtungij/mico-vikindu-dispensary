<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use App\Enums\PayerType;
use App\Enums\VisitPriority;
use App\Enums\VisitStatus;
use App\Enums\VisitType;
use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\ClinicalEncounter;
use App\Models\Department;
use App\Models\FacilitySetting;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use App\Models\Visit;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AppointmentService
{
    public function __construct(
        private readonly AppointmentNumberService $numbers,
        private readonly VisitService $visits,
        private readonly WorkflowService $workflow,
    ) {}

    public function create(array $data, User $actor): Appointment
    {
        return DB::transaction(function () use ($data, $actor): Appointment {
            $facility = currentFacility();
            abort_unless($facility, 403);

            $patient = Patient::query()->where('facility_id', $facility->id)->findOrFail($data['patient_id']);
            $department = Department::query()->where('facility_id', $facility->id)->where('is_active', true)->findOrFail($data['department_id']);
            $staffId = $data['staff_id'] ?? $data['assigned_to_user_id'] ?? null;
            $serviceId = $data['service_id'] ?? null;
            $start = $this->startAt($data);
            $duration = (int) ($data['estimated_duration'] ?? 30);
            $end = $start->copy()->addMinutes($duration);

            if ($staffId) {
                User::query()->whereHas('staffProfile', fn ($query) => $query->where('facility_id', $facility->id))->findOrFail($staffId);
                $this->guardDoctorOverlap($facility->id, (int) $staffId, $start, $end);
            }

            if ($serviceId) {
                Service::query()->where('facility_id', $facility->id)->where('is_active', true)->findOrFail($serviceId);
            }

            $appointment = Appointment::query()->create([
                'facility_id' => $facility->id,
                'patient_id' => $patient->id,
                'appointment_number' => $this->numbers->next($facility->id),
                'department_id' => $department->id,
                'assigned_to_user_id' => $staffId,
                'staff_id' => $staffId,
                'service_id' => $serviceId,
                'appointment_type' => $data['appointment_type'] ?? AppointmentType::GeneralConsultation->value,
                'appointment_date' => $start->toDateString(),
                'appointment_time' => $start->format('H:i:s'),
                'estimated_duration' => $duration,
                'priority' => $data['priority'] ?? 'normal',
                'scheduled_start' => $start,
                'scheduled_end' => $end,
                'status' => AppointmentStatus::Booked,
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
                'reminder_status' => 'pending',
                'reminder_date' => $start->copy()->subDay(),
                'booked_by' => $actor->id,
                'created_by' => $actor->id,
            ]);

            $this->audit($actor, 'appointment_created', $appointment);

            return $appointment->refresh();
        });
    }

    public function update(Appointment $appointment, array $data, User $actor): Appointment
    {
        return DB::transaction(function () use ($appointment, $data, $actor): Appointment {
            $appointment = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);
            abort_unless($appointment->facility_id === currentFacility()?->id, 404);

            $start = $this->startAt($data);
            $duration = (int) ($data['estimated_duration'] ?? $appointment->estimated_duration ?: 30);
            $end = $start->copy()->addMinutes($duration);
            $staffId = $data['staff_id'] ?? $data['assigned_to_user_id'] ?? null;

            if ($staffId) {
                $this->guardDoctorOverlap($appointment->facility_id, (int) $staffId, $start, $end, $appointment->id);
            }

            $appointment->update([
                'patient_id' => $data['patient_id'],
                'department_id' => $data['department_id'],
                'assigned_to_user_id' => $staffId,
                'staff_id' => $staffId,
                'service_id' => $data['service_id'] ?? null,
                'appointment_type' => $data['appointment_type'],
                'appointment_date' => $start->toDateString(),
                'appointment_time' => $start->format('H:i:s'),
                'estimated_duration' => $duration,
                'priority' => $data['priority'] ?? 'normal',
                'scheduled_start' => $start,
                'scheduled_end' => $end,
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
                'updated_by' => $actor->id,
            ]);

            $this->audit($actor, 'appointment_updated', $appointment);

            return $appointment->refresh();
        });
    }

    public function reschedule(Appointment $appointment, array $data, User $actor): Appointment
    {
        return DB::transaction(function () use ($appointment, $data, $actor): Appointment {
            $appointment = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);
            $appointment->update(['status' => AppointmentStatus::Rescheduled, 'updated_by' => $actor->id]);
            $new = $this->create([...$appointment->only(['patient_id','department_id','service_id','appointment_type','priority','reason','notes']), ...$data, 'rescheduled_from' => $appointment->id], $actor);
            $new->update(['rescheduled_from' => $appointment->id]);
            $this->audit($actor, 'appointment_rescheduled', $new, ['from' => $appointment->appointment_number]);

            return $new->refresh();
        });
    }

    public function cancel(Appointment $appointment, string $reason, User $actor): Appointment
    {
        if (blank($reason)) {
            throw ValidationException::withMessages(['cancellation_reason' => 'Sababu ya kughairi miadi inahitajika.']);
        }

        $appointment->update([
            'status' => AppointmentStatus::Cancelled,
            'cancelled_by' => $actor->id,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
            'updated_by' => $actor->id,
        ]);
        $this->audit($actor, 'appointment_cancelled', $appointment, ['reason' => $reason]);

        return $appointment->refresh();
    }

    public function confirm(Appointment $appointment, User $actor): Appointment
    {
        $appointment->update(['status' => AppointmentStatus::Confirmed, 'updated_by' => $actor->id]);
        $this->audit($actor, 'appointment_confirmed', $appointment);

        return $appointment->refresh();
    }

    public function markNoShow(Appointment $appointment, User $actor): Appointment
    {
        $appointment->update(['status' => AppointmentStatus::NoShow, 'updated_by' => $actor->id]);
        $this->audit($actor, 'appointment_no_show', $appointment);

        return $appointment->refresh();
    }

    public function checkIn(Appointment $appointment, User $actor): Appointment
    {
        return DB::transaction(function () use ($appointment, $actor): Appointment {
            $appointment = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);
            abort_unless($appointment->facility_id === currentFacility()?->id, 404);

            if (in_array($appointment->status?->value, [AppointmentStatus::Cancelled->value, AppointmentStatus::NoShow->value], true)) {
                throw ValidationException::withMessages(['appointment' => 'Miadi hii haiwezi ku-check in.']);
            }

            $visit = $appointment->visit;
            if (! $visit) {
                $visit = $this->visits->createVisit($appointment->patient, [
                    'visit_type' => $this->visitTypeFor($appointment)->value,
                    'payer_type' => PayerType::Cash->value,
                    'destination_department_id' => $appointment->department_id,
                    'consultation_service_id' => $appointment->service_id,
                    'priority' => (VisitPriority::tryFrom($appointment->priority ?? 'normal') ?? VisitPriority::Normal)->value,
                    'source' => 'appointment',
                    'reason_for_visit' => $appointment->reason ?: $appointment->appointment_type?->value,
                    'require_payment_before_service' => true,
                    'arrived_at' => now(),
                    'checked_in_at' => now(),
                ], $actor);
            }

            $appointment->update([
                'visit_id' => $visit->id,
                'status' => $visit->visit_status === VisitStatus::AwaitingPayment ? AppointmentStatus::CheckedIn : AppointmentStatus::Waiting,
                'checked_in_by' => $actor->id,
                'checked_in_at' => now(),
                'updated_by' => $actor->id,
            ]);
            $this->audit($actor, 'appointment_checked_in', $appointment, ['visit_id' => $visit->id]);

            return $appointment->refresh();
        });
    }

    public function createFollowUp(ClinicalEncounter $encounter, array $data, $actor): Appointment
    {
        $start = Carbon::parse($data['scheduled_start']);

        return $this->create([
            'patient_id' => $encounter->patient_id,
            'department_id' => $data['department_id'] ?? $encounter->department_id,
            'staff_id' => $data['assigned_to_user_id'] ?? null,
            'appointment_type' => $data['appointment_type'] ?? AppointmentType::OpdFollowUp->value,
            'appointment_date' => $start->toDateString(),
            'appointment_time' => $start->format('H:i'),
            'estimated_duration' => isset($data['scheduled_end']) ? max(5, $start->diffInMinutes(Carbon::parse($data['scheduled_end']))) : 30,
            'priority' => 'normal',
            'reason' => $data['reason'] ?? 'OPD follow-up',
            'notes' => $data['notes'] ?? null,
        ], $actor);
    }

    private function startAt(array $data): Carbon
    {
        $start = Carbon::parse(($data['appointment_date'] ?? now()->toDateString()).' '.($data['appointment_time'] ?? '08:00'));
        if ($start->isPast()) {
            throw ValidationException::withMessages(['appointment_date' => 'Tarehe na muda wa miadi haviwezi kuwa vya nyuma.']);
        }

        return $start;
    }

    private function guardDoctorOverlap(int $facilityId, int $staffId, Carbon $start, Carbon $end, ?int $ignoreId = null): void
    {
        $allowOverlap = filter_var(FacilitySetting::query()->where('facility_id', $facilityId)->where('key', 'appointments_allow_doctor_overlap')->value('value') ?? false, FILTER_VALIDATE_BOOL);
        if ($allowOverlap) {
            return;
        }

        $overlap = Appointment::query()
            ->where('facility_id', $facilityId)
            ->where('staff_id', $staffId)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->whereNotIn('status', [AppointmentStatus::Cancelled->value, AppointmentStatus::NoShow->value, AppointmentStatus::Rescheduled->value])
            ->where('scheduled_start', '<', $end)
            ->where(fn ($query) => $query->whereNull('scheduled_end')->orWhere('scheduled_end', '>', $start))
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages(['staff_id' => 'Daktari ana appointment nyingine muda huo.']);
        }
    }

    private function visitTypeFor(Appointment $appointment): VisitType
    {
        return match ($appointment->appointment_type?->value) {
            'dental', 'dental_review' => VisitType::Dental,
            'anc', 'pnc', 'family_planning', 'child_clinic', 'immunization' => VisitType::Rch,
            'laboratory', 'lab_review' => VisitType::LaboratoryOnly,
            default => VisitType::FollowUp,
        };
    }

    private function audit(User $actor, string $event, Appointment $appointment, array $values = []): void
    {
        ActivityLog::query()->create(['user_id' => $actor->id, 'event' => $event, 'subject_type' => $appointment::class, 'subject_id' => $appointment->id, 'new_values' => $values ?: null]);
    }
}
