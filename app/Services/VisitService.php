<?php

namespace App\Services;

use App\Enums\PayerType;
use App\Enums\PatientStatus;
use App\Enums\VisitStatus;
use App\Models\Department;
use App\Models\Patient;
use App\Models\Service;
use App\Models\Visit;
use App\Models\VisitMovement;
use Illuminate\Validation\ValidationException;

class VisitService
{
    public function __construct(private readonly VisitNumberService $numbers) {}

    public function createVisit(Patient $patient, array $data, $actor, bool $overrideActive = false): Visit
    {
        $this->validateNoActiveVisit($patient, $overrideActive);
        if (in_array($patient->patient_status, [PatientStatus::Deceased, PatientStatus::Blocked], true)) {
            throw ValidationException::withMessages(['patient' => 'Patient hawezi kufunguliwa visit mpya.']);
        }

        $department = Department::query()->where('facility_id', $patient->facility_id)->where('is_active', true)->find($data['destination_department_id']);
        if (! $department) {
            throw ValidationException::withMessages(['destination_department_id' => 'Department si active au si ya facility hii.']);
        }
        if (($data['consultation_service_id'] ?? null) && ! Service::query()->where('facility_id', $patient->facility_id)->where('is_active', true)->where('id', $data['consultation_service_id'])->exists()) {
            throw ValidationException::withMessages(['consultation_service_id' => 'Huduma si active au si ya facility hii.']);
        }

        $status = $this->determineInitialStatus(PayerType::from($data['payer_type']), (bool) ($data['require_payment_before_service'] ?? true), $department);

        $visit = Visit::query()->create([
            ...$data,
            'facility_id' => $patient->facility_id,
            'patient_id' => $patient->id,
            'visit_number' => $this->numbers->next($patient->facility_id),
            'current_department_id' => $department->id,
            'visit_status' => $status,
            'registered_at' => now(),
            'created_by' => $actor->id,
        ]);

        VisitMovement::query()->create([
            'facility_id' => $visit->facility_id,
            'visit_id' => $visit->id,
            'patient_id' => $patient->id,
            'to_department_id' => $department->id,
            'movement_type' => 'registration',
            'status' => 'completed',
            'moved_by' => $actor->id,
            'moved_at' => now(),
        ]);

        return $visit;
    }

    public function validateNoActiveVisit(Patient $patient, bool $override = false): void
    {
        if (! $override && $patient->visits()->whereNotIn('visit_status', ['completed', 'cancelled', 'discharged', 'referred'])->exists()) {
            throw ValidationException::withMessages(['visit' => 'Patient ana active visit.']);
        }
    }

    public function determineInitialStatus(PayerType $payerType, bool $requirePaymentBeforeService, Department $department): VisitStatus
    {
        if ($payerType === PayerType::Cash && $requirePaymentBeforeService) {
            return VisitStatus::AwaitingPayment;
        }

        return $department->clinical_department ? VisitStatus::AwaitingTriage : VisitStatus::AwaitingDepartment;
    }

    public function transferVisit(Visit $visit, Department $department, string $reason, $actor): Visit
    {
        VisitMovement::query()->create([
            'facility_id' => $visit->facility_id,
            'visit_id' => $visit->id,
            'patient_id' => $visit->patient_id,
            'from_department_id' => $visit->current_department_id,
            'to_department_id' => $department->id,
            'movement_type' => 'queue_transfer',
            'status' => 'completed',
            'reason' => $reason,
            'moved_by' => $actor->id,
            'moved_at' => now(),
        ]);
        $visit->update(['current_department_id' => $department->id, 'destination_department_id' => $department->id, 'updated_by' => $actor->id]);
        return $visit->refresh();
    }
}
