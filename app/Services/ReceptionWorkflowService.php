<?php

namespace App\Services;

use App\Enums\VisitStatus;
use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\Facility;
use App\Models\LaboratoryOrder;
use App\Models\Visit;
use App\Models\VisitMovement;
use App\Models\WorkflowSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReceptionWorkflowService
{
    public function __construct(
        private readonly PatientService $patients,
        private readonly PatientPayerService $payers,
        private readonly VisitService $visits,
        private readonly InvoiceService $invoices,
        private readonly QueueService $queues,
        private readonly ReceptionChargeService $charges,
        private readonly LaboratoryOrderService $laboratoryOrders,
        private readonly PatientDuplicateDetectionService $duplicates,
    ) {}

    public function registerNewPatientAndVisit(array $patientData, array $payerData, array $visitData, array $serviceIds = [], $actor = null, array $duplicateReview = []): array
    {
        if (($visitData['visit_type'] ?? 'new_patient') === 'returning_patient') {
            throw ValidationException::withMessages(['visit_type' => 'Returning visit lazima ichague mgonjwa aliyesajiliwa tayari.']);
        }
        if ($existing = $this->existingRegistration($visitData)) {
            return $existing;
        }

        return DB::transaction(function () use ($patientData, $payerData, $visitData, $serviceIds, $actor, $duplicateReview): array {
            Facility::query()->whereKey(currentFacility()?->id)->lockForUpdate()->firstOrFail();
            $duplicateMatch = ($visitData['visit_type'] ?? 'new_patient') === 'emergency'
                ? $this->duplicates->detect([])
                : $this->validateDuplicateReview($patientData, $payerData, $actor, $duplicateReview);
            $patient = $this->patients->createPatient($patientData, $actor);
            $payerProfile = $this->payers->createProfile($patient, $payerData, $actor);
            $visitData['patient_payer_profile_id'] = $payerProfile->id;
            $visitData['payer_type'] = $payerProfile->payer_type->value;
            $destination = $this->charges->destination($patient->facility, (int) $visitData['destination_department_id']);
            $consultation = $this->charges->resolveConsultationService($patient->facility, $destination, $visitData['consultation_service_id'] ?? null, $visitData['visit_type'] ?? 'new_patient');
            $visitData['consultation_service_id'] = $consultation?->id;
            [$registration] = $this->charges->validateChargeConfiguration($patient->facility, true, $destination, $consultation, $payerProfile->payer_type, $payerProfile->insurance_provider_id, $payerProfile->corporate_account_id, $visitData['visit_type'] ?? 'new_patient');
            $visit = $this->visits->createVisit($patient, $visitData, $actor);
            $invoice = $this->invoices->createVisitInvoice($visit, [], $actor);
            $invoice = $this->charges->createInitialInvoiceItems($invoice, $registration, $consultation, true, $destination, $actor);
            $laboratoryOrder = null;
            if (strtoupper((string) $destination->code) === 'LAB') {
                if ($serviceIds === []) {
                    throw ValidationException::withMessages([
                        'selectedLaboratoryTestIds' => 'Chagua angalau kipimo kimoja cha maabara.',
                    ]);
                }
                $laboratoryOrder = $this->laboratoryOrders->createDirectOrder($visit, $invoice, $serviceIds, $actor);
                $invoice->refresh();
            }
            $visit = $this->applyPostChargeStatus($visit, $destination, (float) $invoice->items()->sum('patient_amount'), $this->paymentBeforeService($visitData, $patient->facility_id), $actor);
            $queue = null;
            if ($laboratoryOrder && (float) $invoice->balance_amount <= 0) {
                $this->laboratoryOrders->activateLaboratoryQueue($laboratoryOrder, $actor);
                $queue = $visit->queues()->where('department_id', $destination->id)->latest()->first();
            } elseif (! $laboratoryOrder && $this->shouldCreateDestinationQueue($visit, $destination)) {
                $queue = $this->queues->createQueue($visit->load('destinationDepartment'), $actor);
            }

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
            if ($duplicateMatch['status'] !== 'none') {
                ActivityLog::query()->create([
                    'user_id' => $actor->id,
                    'event' => 'patient_created_despite_duplicate_match',
                    'subject_type' => $patient::class,
                    'subject_id' => $patient->id,
                    'old_values' => [],
                    'new_values' => [
                        'facility_id' => $patient->facility_id,
                        'matched_patient_ids' => $duplicateMatch['matched_patient_ids'],
                        'match_severity' => $duplicateMatch['status'],
                        'match_reasons' => $duplicateMatch['reasons'],
                        'override_reason' => $duplicateReview['reason'] ?? null,
                        'created_patient_id' => $patient->id,
                    ],
                    'ip_address' => request()?->ip(),
                    'user_agent' => request()?->userAgent(),
                ]);
            }

            return compact('patient', 'payerProfile', 'visit', 'invoice', 'queue', 'laboratoryOrder');
        });
    }

    public function openReturningPatientVisit($patient, array $payerData, array $visitData, array $serviceIds, $actor, ?string $activeVisitOverrideReason = null): array
    {
        abort_unless($patient->facility_id === currentFacility()?->id && $actor->belongsToCurrentFacility(), 403);
        $visitData['visit_type'] = 'returning_patient';
        if ($existing = $this->existingRegistration($visitData)) {
            return $existing;
        }
        $activeVisit = $patient->activeVisit()->first();
        if ($activeVisit) {
            if (! $actor->can('reception.override-active-visit')) {
                throw ValidationException::withMessages(['patient' => 'Mgonjwa tayari ana visit active '.$activeVisit->visit_number.'. Fungua visit hiyo au omba ruhusa ya kuunda nyingine.']);
            }
            if (mb_strlen(trim((string) $activeVisitOverrideReason)) < 10) {
                throw ValidationException::withMessages(['activeVisitOverrideReason' => 'Eleza sababu ya kuunda visit nyingine kwa angalau herufi 10.']);
            }
        }

        $payerProfile = $patient->primaryPayerProfile ?? $this->payers->createProfile($patient, $payerData, $actor);
        $visitData['patient_payer_profile_id'] = $payerProfile->id;
        $visitData['payer_type'] = $payerProfile->payer_type->value;

        return DB::transaction(function () use ($patient, $payerProfile, $visitData, $serviceIds, $actor, $activeVisit, $activeVisitOverrideReason): array {
            $destination = $this->charges->destination($patient->facility, (int) $visitData['destination_department_id']);
            $consultation = $this->charges->resolveConsultationService($patient->facility, $destination, $visitData['consultation_service_id'] ?? null, $visitData['visit_type'] ?? 'returning_patient');
            $visitData['consultation_service_id'] = $consultation?->id;
            [$registration] = $this->charges->validateChargeConfiguration($patient->facility, false, $destination, $consultation, $payerProfile->payer_type, $payerProfile->insurance_provider_id, $payerProfile->corporate_account_id, $visitData['visit_type'] ?? 'returning_patient');
            $visit = $this->visits->createVisit($patient, $visitData, $actor, $actor->can('reception.override-active-visit'));
            $invoice = $this->invoices->createVisitInvoice($visit, [], $actor);
            $invoice = $this->charges->createInitialInvoiceItems($invoice, $registration, $consultation, false, $destination, $actor);
            $laboratoryOrder = null;
            if (strtoupper((string) $destination->code) === 'LAB') {
                if ($serviceIds === []) {
                    throw ValidationException::withMessages([
                        'selectedLaboratoryTestIds' => 'Chagua angalau kipimo kimoja cha maabara.',
                    ]);
                }
                $laboratoryOrder = $this->laboratoryOrders->createDirectOrder($visit, $invoice, $serviceIds, $actor);
                $invoice->refresh();
            }
            $visit = $this->applyPostChargeStatus($visit, $destination, (float) $invoice->items()->sum('patient_amount'), $this->paymentBeforeService($visitData, $patient->facility_id), $actor);
            $queue = null;
            if ($laboratoryOrder && (float) $invoice->balance_amount <= 0) {
                $this->laboratoryOrders->activateLaboratoryQueue($laboratoryOrder, $actor);
                $queue = $visit->queues()->where('department_id', $destination->id)->latest()->first();
            } elseif (! $laboratoryOrder && $this->shouldCreateDestinationQueue($visit, $destination)) {
                $queue = $this->queues->createQueue($visit->load('destinationDepartment'), $actor);
            }

            ActivityLog::query()->create([
                'user_id' => $actor->id,
                'event' => 'visit_created',
                'subject_type' => Visit::class,
                'subject_id' => $visit->id,
                'old_values' => [],
                'new_values' => ['patient_id' => $patient->id, 'invoice_id' => $invoice->id, 'queue_id' => $queue?->id, 'visit_type' => 'returning_patient'],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
            if ($activeVisit) {
                ActivityLog::query()->create([
                    'user_id' => $actor->id,
                    'event' => 'active_visit_override',
                    'subject_type' => Visit::class,
                    'subject_id' => $visit->id,
                    'old_values' => ['active_visit_id' => $activeVisit->id],
                    'new_values' => [
                        'facility_id' => $visit->facility_id,
                        'patient_id' => $patient->id,
                        'previous_active_visit_id' => $activeVisit->id,
                        'new_visit_id' => $visit->id,
                        'reason' => trim((string) $activeVisitOverrideReason),
                    ],
                    'ip_address' => request()?->ip(),
                    'user_agent' => request()?->userAgent(),
                ]);
            }

            return compact('patient', 'payerProfile', 'visit', 'invoice', 'queue', 'laboratoryOrder');
        });
    }

    private function validateDuplicateReview(array $patientData, array $payerData, $actor, array $review): array
    {
        $matches = $this->duplicates->detect($patientData, $payerData);
        if ($matches['status'] === 'none') {
            return $matches;
        }

        $confirmed = (bool) ($review['confirmed'] ?? false);
        $reason = trim((string) ($review['reason'] ?? ''));
        if ($matches['status'] === 'exact') {
            $immutableIdentifierMatch = collect($matches['reasons'])->flatten()->contains(fn (string $code) => in_array($code, ['exact_nida', 'exact_passport'], true));
            if ($immutableIdentifierMatch) {
                throw ValidationException::withMessages(['duplicate' => 'NIDA au passport hii tayari imetumika. Chagua rekodi iliyopo; haiwezi kurudiwa.']);
            }
            if (! $actor?->can('patients.override-duplicate-warning')) {
                throw ValidationException::withMessages(['duplicate' => 'Mgonjwa huyu anaonekana tayari yupo kwenye mfumo. Chagua rekodi iliyopo.']);
            }
            if (! $confirmed || mb_strlen($reason) < 10) {
                throw ValidationException::withMessages(['duplicateOverrideReason' => 'Thibitisha kuwa huyu ni mgonjwa tofauti na eleza sababu kwa angalau herufi 10.']);
            }
        } elseif ($matches['status'] === 'probable' && (! $confirmed || mb_strlen($reason) < 5)) {
            throw ValidationException::withMessages(['duplicateOverrideReason' => 'Kuna mgonjwa anayefanana. Eleza kwa nini huyu ni mgonjwa tofauti.']);
        } elseif ($matches['status'] === 'weak' && ! $confirmed) {
            throw ValidationException::withMessages(['duplicate' => 'Hakiki rekodi zinazofanana, kisha thibitisha kuendelea kama mgonjwa mpya.']);
        }

        return $matches;
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
        $triage = $status === VisitStatus::AwaitingTriage
            ? Department::query()->where('facility_id', $visit->facility_id)->where('code', 'TRI')->first()
            : null;
        $toDepartment = match ($status) {
            VisitStatus::AwaitingPayment => $billing ?? $destination,
            VisitStatus::AwaitingTriage => $triage ?? $destination,
            default => $destination,
        };

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

    private function paymentBeforeService(array $visitData, int $facilityId): bool
    {
        if (($visitData['visit_type'] ?? null) !== 'emergency') {
            return (bool) ($visitData['require_payment_before_service'] ?? true);
        }

        $bypass = WorkflowSetting::query()->where('facility_id', $facilityId)->where('key', 'allow_emergency_override')->value('value');

        return ! filter_var($bypass ?? true, FILTER_VALIDATE_BOOL);
    }

    private function shouldCreateDestinationQueue(Visit $visit, Department $destination): bool
    {
        return $visit->visit_status === VisitStatus::AwaitingDepartment && $destination->queue_enabled;
    }

    private function existingRegistration(array $visitData): ?array
    {
        $key = $visitData['registration_idempotency_key'] ?? null;
        if (! $key) {
            return null;
        }
        $visit = Visit::query()
            ->where('facility_id', currentFacility()?->id)
            ->where('registration_idempotency_key', $key)
            ->with(['patient.primaryPayerProfile', 'invoice', 'queues'])
            ->first();
        if (! $visit) {
            return null;
        }

        return [
            'patient' => $visit->patient,
            'payerProfile' => $visit->payerProfile,
            'visit' => $visit,
            'invoice' => $visit->invoice,
            'queue' => $visit->queues->last(),
            'laboratoryOrder' => LaboratoryOrder::query()
                ->where('visit_id', $visit->id)
                ->where('source', LaboratoryOrder::SOURCE_RECEPTION_DIRECT)
                ->first(),
        ];
    }
}
