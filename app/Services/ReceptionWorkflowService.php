<?php

namespace App\Services;

use App\Enums\PayerType;
use App\Enums\VisitStatus;
use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\Visit;
use App\Models\VisitMovement;
use Illuminate\Support\Facades\DB;

class ReceptionWorkflowService
{
    public function __construct(
        private readonly PatientService $patients,
        private readonly PatientPayerService $payers,
        private readonly VisitService $visits,
        private readonly InvoiceService $invoices,
        private readonly QueueService $queues,
        private readonly ReceptionChargeService $charges,
    ) {}

    public function registerNewPatientAndVisit(array $patientData, array $payerData, array $visitData, array $serviceIds = [], $actor = null): array
    {
        return DB::transaction(function () use ($patientData, $payerData, $visitData, $actor): array {
            $patient = $this->patients->createPatient($patientData, $actor);
            $payerProfile = $this->payers->createProfile($patient, $payerData, $actor);
            $visitData['patient_payer_profile_id'] = $payerProfile->id;
            $visitData['payer_type'] = $payerProfile->payer_type->value;
            $destination = $this->charges->destination($patient->facility, (int) $visitData['destination_department_id']);
            $consultation = $this->charges->resolveConsultationService($patient->facility, $destination, $visitData['consultation_service_id'] ?? null);
            [$registration] = $this->charges->validateChargeConfiguration($patient->facility, true, $destination, $consultation, $payerProfile->payer_type, $payerProfile->insurance_provider_id, $payerProfile->corporate_account_id);
            $visit = $this->visits->createVisit($patient, $visitData, $actor);
            $invoice = $this->invoices->createVisitInvoice($visit, [], $actor);
            $invoice = $this->charges->createInitialInvoiceItems($invoice, $registration, $consultation, true, $destination, $actor);
            $visit = $this->applyPostChargeStatus($visit, $destination, (float) $invoice->items()->sum('patient_amount'), (bool) ($visitData['require_payment_before_service'] ?? true), $actor);
            $queue = $this->shouldCreateDestinationQueue($visit, $destination) ? $this->queues->createQueue($visit->load('destinationDepartment'), $actor) : null;

            ActivityLog::query()->create([
                'user_id' => $actor->id,
                'event' => 'visit_created',
                'subject_type' => Visit::class,
                'subject_id' => $visit->id,
                'old_values' => [],
                'new_values' => ['patient_id' => $patient->id, 'invoice_id' => $invoice->id, 'queue_id' => $queue?->id],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return compact('patient', 'payerProfile', 'visit', 'invoice', 'queue');
        });
    }

    public function openReturningPatientVisit($patient, array $payerData, array $visitData, array $serviceIds, $actor): array
    {
        $payerProfile = $patient->primaryPayerProfile ?? $this->payers->createProfile($patient, $payerData, $actor);
        $visitData['patient_payer_profile_id'] = $payerProfile->id;
        $visitData['payer_type'] = $payerProfile->payer_type->value;

        return DB::transaction(function () use ($patient, $payerProfile, $visitData, $actor): array {
            $destination = $this->charges->destination($patient->facility, (int) $visitData['destination_department_id']);
            $consultation = $this->charges->resolveConsultationService($patient->facility, $destination, $visitData['consultation_service_id'] ?? null);
            [$registration] = $this->charges->validateChargeConfiguration($patient->facility, false, $destination, $consultation, $payerProfile->payer_type, $payerProfile->insurance_provider_id, $payerProfile->corporate_account_id);
            $visit = $this->visits->createVisit($patient, $visitData, $actor);
            $invoice = $this->invoices->createVisitInvoice($visit, [], $actor);
            $invoice = $this->charges->createInitialInvoiceItems($invoice, $registration, $consultation, false, $destination, $actor);
            $visit = $this->applyPostChargeStatus($visit, $destination, (float) $invoice->items()->sum('patient_amount'), (bool) ($visitData['require_payment_before_service'] ?? true), $actor);
            $queue = $this->shouldCreateDestinationQueue($visit, $destination) ? $this->queues->createQueue($visit->load('destinationDepartment'), $actor) : null;

            return compact('patient', 'payerProfile', 'visit', 'invoice', 'queue');
        });
    }

    public function quickRegister(array $data, $actor): array
    {
        [$first, $last] = array_pad(explode(' ', trim($data['full_name']), 2), 2, 'Unknown');

        return $this->registerNewPatientAndVisit([
            'first_name' => $first,
            'last_name' => $last,
            'gender' => $data['gender'],
            'age_years' => $data['age_years'] ?? null,
            'date_of_birth_is_estimated' => true,
            'primary_phone' => $data['phone'] ?? null,
            'patient_status' => 'active',
            'profile_incomplete' => true,
        ], [
            'payer_type' => $data['payer_type'],
            'is_primary' => true,
        ], [
            'visit_type' => 'emergency',
            'payer_type' => $data['payer_type'],
            'destination_department_id' => $data['destination_department_id'],
            'consultation_service_id' => $data['consultation_service_id'] ?? null,
            'priority' => $data['priority'] ?? 'urgent',
            'source' => 'walk_in',
            'reason_for_visit' => $data['reason_for_visit'] ?? null,
            'require_payment_before_service' => false,
        ], [], $actor);
    }

    private function applyPostChargeStatus(Visit $visit, Department $destination, float $patientAmount, bool $paymentFirst, $actor): Visit
    {
        $billing = Department::query()->where('facility_id', $visit->facility_id)->where('code', 'BIL')->first();
        $status = $paymentFirst && $patientAmount > 0
            ? VisitStatus::AwaitingPayment
            : ($destination->requires_triage ? VisitStatus::AwaitingTriage : VisitStatus::AwaitingDepartment);
        $toDepartment = $status === VisitStatus::AwaitingPayment ? ($billing ?? $destination) : $destination;

        if ($visit->current_department_id !== $toDepartment->id || $visit->visit_status !== $status) {
            VisitMovement::query()->create([
                'facility_id' => $visit->facility_id,
                'visit_id' => $visit->id,
                'patient_id' => $visit->patient_id,
                'from_department_id' => $visit->current_department_id,
                'to_department_id' => $toDepartment->id,
                'movement_type' => $status === VisitStatus::AwaitingPayment ? 'reception_to_billing' : 'reception_to_destination',
                'status' => 'completed',
                'reason' => $status === VisitStatus::AwaitingPayment ? 'Payment required before service' : 'Reception registration completed',
                'moved_by' => $actor->id,
                'moved_at' => now(),
            ]);
        }

        $visit->update([
            'visit_status' => $status,
            'current_department_id' => $toDepartment->id,
            'updated_by' => $actor->id,
        ]);

        return $visit->refresh();
    }

    private function shouldCreateDestinationQueue(Visit $visit, Department $destination): bool
    {
        return $visit->visit_status === VisitStatus::AwaitingDepartment && $destination->queue_enabled;
    }
}
