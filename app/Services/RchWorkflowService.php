<?php

namespace App\Services;

use App\Enums\ClinicalEncounterStatus;
use App\Enums\ClinicalEncounterType;
use App\Enums\ClinicalOutcome;
use App\Enums\PayerType;
use App\Enums\PrescriptionStatus;
use App\Enums\ProcedureOrderStatus;
use App\Enums\RchService;
use App\Enums\ServiceType;
use App\Enums\VisitStatus;
use App\Models\ActivityLog;
use App\Models\AncVisit;
use App\Models\Appointment;
use App\Models\ChildGrowthMeasurement;
use App\Models\ClinicalEncounter;
use App\Models\ClinicalProcedureOrder;
use App\Models\Department;
use App\Models\FamilyPlanningVisit;
use App\Models\Prescription;
use App\Models\RchEncounter;
use App\Models\Service;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RchWorkflowService
{
    public function __construct(
        private readonly RchEncounterNumberService $numbers,
        private readonly WorkflowService $workflow,
        private readonly AppointmentService $appointments,
        private readonly VisitClosureService $closure,
    ) {}

    public function startEncounter(Visit $visit, User $actor): RchEncounter
    {
        Gate::forUser($actor)->authorize('rch.start-encounter');

        return DB::transaction(function () use ($visit, $actor): RchEncounter {
            $visit = Visit::query()->lockForUpdate()->findOrFail($visit->id);
            $this->assertRchVisit($visit, $actor);
            $existing = RchEncounter::query()->forCurrentFacility()->where('visit_id', $visit->id)->latest('id')->first();
            if ($existing) {
                $this->assertEncounter($existing, $visit);

                return $existing;
            }

            $service = $visit->consultationService;
            $definition = $service?->facility_id === $visit->facility_id ? RchService::tryFrom($service->code) : null;
            if (! $this->initialChargeCleared($visit)
                || $visit->current_department_id !== $visit->destination_department_id
                || ! $visit->latestCompletedTriageAssessment()->where('triage_assessments.patient_id', $visit->patient_id)->exists()
                || ! in_array($visit->visit_status, [VisitStatus::InQueue, VisitStatus::Called, VisitStatus::Serving, VisitStatus::InProgress, VisitStatus::AwaitingDepartment], true)) {
                throw ValidationException::withMessages(['visit' => 'Clear the selected RCH service and complete Triage before starting the encounter.']);
            }

            $clinical = ClinicalEncounter::query()->create([
                'facility_id' => $visit->facility_id,
                'visit_id' => $visit->id,
                'patient_id' => $visit->patient_id,
                'department_id' => $visit->destination_department_id,
                'status' => ClinicalEncounterStatus::InProgress,
                'encounter_type' => ClinicalEncounterType::Rch,
                'encounter_number' => app(ClinicalEncounterNumberService::class)->next($visit->facility_id),
                'provider_user_id' => $actor->id,
                'started_at' => now(),
                'created_by' => $actor->id,
            ]);

            $encounter = RchEncounter::query()->create([
                'facility_id' => $visit->facility_id,
                'visit_id' => $visit->id,
                'clinical_encounter_id' => $clinical->id,
                'patient_id' => $visit->patient_id,
                'encounter_type' => $definition?->encounterType() ?? 'rch_general',
                'encounter_number' => $this->numbers->next($visit->facility_id),
                'provider_user_id' => $actor->id,
                'status' => 'in_progress',
                'started_at' => now(),
                'created_by' => $actor->id,
            ]);

            $visit->update(['visit_status' => VisitStatus::InConsultation, 'current_assigned_user_id' => $actor->id, 'updated_by' => $actor->id]);
            $this->audit('rch_encounter_started', $encounter, $actor);

            return $encounter;
        }, 3);
    }

    public function initialChargeCleared(Visit $visit): bool
    {
        $service = $visit->consultationService;
        if (! $service || $service->facility_id !== $visit->facility_id) {
            return false;
        }
        $legacy = $service->code === 'RCHCON' && $service->department_id === $visit->destination_department_id;
        if (! $legacy && ! RchService::tryFrom($service->code)) {
            return false;
        }
        $invoice = $visit->invoice;
        if (! $invoice || $invoice->facility_id !== $visit->facility_id || $invoice->patient_id !== $visit->patient_id
            || in_array($invoice->status, ['cancelled', 'void', 'voided', 'refunded', 'reversed', 'replaced'], true)) {
            return false;
        }

        return $invoice->items()->where('service_id', $service->id)
            ->where('metadata->charge_source', $legacy ? 'reception_consultation' : 'reception_rch_service')
            ->whereNotIn('status', ['cancelled', 'reversed', 'non_billable'])
            ->whereColumn('paid_amount', '>=', 'patient_amount')->exists();
    }

    public function withOpenEncounter(RchEncounter $encounter, User $actor, callable $action): mixed
    {
        return $this->withEncounter($encounter, $actor, function (RchEncounter $encounter, Visit $visit) use ($actor, $action): mixed {
            Gate::forUser($actor)->authorize('update', $encounter);
            abort_unless($encounter->status === 'in_progress' && ! $encounter->clinicalEncounter->isReadOnly(), 403);

            return $action($encounter, $visit);
        });
    }

    public function withEncounter(RchEncounter $encounter, User $actor, callable $action, bool $allowCompleted = false): mixed
    {
        Gate::forUser($actor)->authorize('rch.start-encounter');

        return DB::transaction(function () use ($encounter, $actor, $action, $allowCompleted): mixed {
            $visit = Visit::query()->lockForUpdate()->findOrFail($encounter->visit_id);
            $this->assertRchVisit($visit, $actor);
            $encounter = RchEncounter::query()->forCurrentFacility()->lockForUpdate()->findOrFail($encounter->id);
            $this->assertEncounter($encounter, $visit);
            abort_if(in_array($visit->visit_status, [VisitStatus::Cancelled, VisitStatus::Referred, VisitStatus::Discharged], true)
                || (! $allowCompleted && $visit->visit_status === VisitStatus::Completed), 403);

            return $action($encounter, $visit);
        }, 3);
    }

    public function sendPrescription(Prescription $prescription, User $actor): void
    {
        Gate::forUser($actor)->authorize('update', $prescription);
        if ($prescription->status !== PrescriptionStatus::Draft) {
            return;
        }

        app(PrescriptionService::class)->finalizePrescription($prescription, $actor);
        $billing = app(PrescriptionBillingService::class);
        if ($billing->hasFinanciallyDispensableItems($prescription)) {
            $billing->releasePrescription($prescription, $actor);
        }
        if ($prescription->refresh()->status === PrescriptionStatus::AwaitingPayment) {
            $department = Department::query()->where('facility_id', $prescription->facility_id)->where('code', 'BIL')->where('is_active', true)->firstOrFail();
            $this->workflow->createQueue($prescription->visit, $department, $actor, VisitStatus::AwaitingPayment, 'RCH prescription payment required', true, false);
        }
    }

    public function orderProcedures(RchEncounter $encounter, Visit $visit, array $serviceIds, User $actor, ?string $instructions): void
    {
        Gate::forUser($actor)->authorize('create', ClinicalProcedureOrder::class);
        $clinical = $encounter->clinicalEncounter;
        $services = Service::query()->where('facility_id', $visit->facility_id)->where('is_active', true)
            ->where('service_type', ServiceType::Procedure)->whereIn('id', $serviceIds)->with('department')->get();
        if ($services->count() !== count($serviceIds)) {
            throw ValidationException::withMessages(['procedureIds' => __('rch.invalid_procedure')]);
        }

        $procedures = app(ProcedureOrderService::class);
        $needsPayment = false;
        foreach ($services as $service) {
            if ($clinical->procedureOrders()->where('service_id', $service->id)->exists()) {
                continue;
            }
            $receptionCharge = $visit->invoice?->items()->where('service_id', $service->id)
                ->where('metadata->charge_source', 'reception_rch_service')->whereNull('reference_id')->first();
            if (! $receptionCharge && $visit->payer_type !== PayerType::Exempted) {
                app(ReceptionChargeService::class)->resolveConsultationPrice(
                    $service, $visit->payer_type, $visit->payerProfile?->insurance_provider_id, $visit->payerProfile?->corporate_account_id,
                );
            }
            $order = $procedures->createOrder($clinical, [
                'service_id' => $service->id, 'instructions' => $instructions,
            ], $actor, $receptionCharge);
            if ($order->status === ProcedureOrderStatus::AwaitingPayment) {
                $needsPayment = true;
            } elseif ($service->department?->queue_enabled && $service->department_id !== $clinical->department_id) {
                $this->workflow->createQueue($visit, $service->department, $actor, VisitStatus::AwaitingDepartment, 'RCH procedure ordered', true, false);
            }
        }
        if ($needsPayment) {
            $procedures->releasePaidInvoice($visit->invoice, $actor);
            if ((float) $visit->invoice->refresh()->balance_amount > 0) {
                $billing = Department::query()->where('facility_id', $visit->facility_id)->where('code', 'BIL')->where('is_active', true)->firstOrFail();
                $this->workflow->createQueue($visit, $billing, $actor, VisitStatus::AwaitingPayment, 'RCH procedure payment required', true, false);
            }
        }
    }

    public function scheduleFollowUp(RchEncounter $encounter, Visit $visit, array $data, User $actor): Appointment
    {
        Gate::forUser($actor)->authorize('appointments.create');

        $appointment = $encounter->clinicalEncounter->appointments()->where('scheduled_start', $data['scheduled_start'])
            ->whereNotIn('status', ['cancelled', 'no_show', 'rescheduled'])->first();
        if ($appointment) {
            return $appointment;
        }

        $service = app(ReceptionChargeService::class)->resolveConsultationService($visit->destinationDepartment->facility, $visit->destinationDepartment, $data['service_id']);
        $definition = RchService::from($service->code);
        $appointment = $this->appointments->create([
            'patient_id' => $visit->patient_id,
            'department_id' => $visit->destination_department_id,
            'service_id' => $service->id,
            'appointment_type' => match ($definition) {
                RchService::ChildMonitoring => 'child_clinic',
                RchService::Anc => 'anc',
                RchService::Pnc => 'pnc',
                RchService::FamilyPlanning => 'family_planning',
                default => 'follow_up_visit',
            },
            'appointment_date' => $data['appointment_date'],
            'appointment_time' => $data['appointment_time'],
            'reason' => $data['reason'],
        ], $actor);
        $appointment->update(['clinical_encounter_id' => $encounter->clinical_encounter_id]);
        $encounter->update(['follow_up_required' => true, 'follow_up_date' => $data['appointment_date'], 'updated_by' => $actor->id]);

        return $appointment;
    }

    public function completeEncounter(RchEncounter $encounter, User $actor, array $notes): Visit
    {
        return $this->withEncounter($encounter, $actor, function (RchEncounter $encounter, Visit $visit) use ($actor, $notes): Visit {
            Gate::forUser($actor)->authorize('complete', $encounter);
            $clinical = $encounter->clinicalEncounter;
            if ($encounter->status === 'completed' && $clinical->status === ClinicalEncounterStatus::Completed) {
                return $visit;
            }
            Gate::forUser($actor)->authorize('update', $encounter);
            abort_unless($encounter->status === 'in_progress' && $clinical->status === ClinicalEncounterStatus::InProgress
                && ! $clinical->isReadOnly(), 403);
            if ($visit->consultationService?->code === RchService::Ultrasound->value
                && ! $clinical->procedureOrders()->where('service_id', $visit->consultation_service_id)->exists()) {
                throw ValidationException::withMessages(['procedureIds' => __('rch.prepaid_procedure_required')]);
            }
            if ($clinical->prescriptions()->where('status', PrescriptionStatus::Draft)->exists()) {
                throw ValidationException::withMessages(['encounter' => __('rch.pending_prescription_drafts')]);
            }

            $pnc = $encounter->encounter_type === RchService::Pnc->encounterType() ? $encounter->pncVisit()
                ->where('facility_id', $encounter->facility_id)->where('patient_id', $encounter->patient_id)
                ->where('visit_id', $visit->id)->first() : null;
            if (blank($notes['clinical_summary'] ?? null)) {
                $notes['clinical_summary'] = match ($encounter->encounter_type) {
                    'pnc' => $pnc?->clinical_notes ?? $pnc?->maternal_observations,
                    'anc' => AncVisit::query()->forCurrentFacility()->where('rch_encounter_id', $encounter->id)
                        ->where('patient_id', $encounter->patient_id)->latest('id')->value('assessment'),
                    'family_planning' => FamilyPlanningVisit::query()->forCurrentFacility()->where('rch_encounter_id', $encounter->id)
                        ->where('patient_id', $encounter->patient_id)->latest('id')->value('notes'),
                    'child_growth' => ChildGrowthMeasurement::query()->forCurrentFacility()->where('rch_encounter_id', $encounter->id)
                        ->where('child_patient_id', $encounter->patient_id)->latest('id')->value('notes'),
                    default => null,
                };
            }
            if ($pnc && blank($notes['treatment_plan'] ?? null)) {
                $notes['treatment_plan'] = $pnc->treatment_plan;
            }

            $clinical->fill([
                ...$notes,
                'follow_up_required' => $encounter->follow_up_required,
                'follow_up_date' => $encounter->follow_up_date,
                'outcome' => $encounter->follow_up_required ? ClinicalOutcome::FollowUp : ClinicalOutcome::DischargedHome,
            ]);
            $clinicalService = app(ClinicalEncounterService::class);
            $clinicalService->validateRchSignOff($clinical);
            $completedAt = now();
            $encounter->update([
                ...$notes,
                'status' => 'completed',
                'completed_at' => $completedAt,
                'signed_off_by' => $actor->id,
                'signed_off_at' => $completedAt,
                'updated_by' => $actor->id,
            ]);
            $clinical->forceFill([
                'status' => ClinicalEncounterStatus::Completed,
                'completed_at' => $completedAt,
                'completed_by' => $actor->id,
                'signed_off_by' => $actor->id,
                'signed_off_at' => $completedAt,
                'signed_content_hash' => $clinicalService->clinicalContentHash($clinical),
                'updated_by' => $actor->id,
            ])->save();

            if ($pnc) {
                $previous = $pnc->status;
                $pnc->update(['status' => 'completed']);
                ActivityLog::query()->create([
                    'user_id' => $actor->id, 'event' => 'pnc_visit_completed',
                    'subject_type' => $pnc::class, 'subject_id' => $pnc->id,
                    'old_values' => ['status' => $previous], 'new_values' => ['status' => $pnc->status],
                ]);
            }
            $this->closure->completeDepartmentQueues($visit, 'RCH', $actor);
            if ($clinical->procedureOrders()->whereIn('status', [ProcedureOrderStatus::Ordered, ProcedureOrderStatus::Scheduled, ProcedureOrderStatus::InProgress])
                ->whereHas('service', fn ($query) => $query->where('department_id', $clinical->department_id))->exists()) {
                $this->workflow->createQueue($visit, $visit->destinationDepartment, $actor, VisitStatus::AwaitingDepartment, 'RCH procedure pending', true, false);
            }
            $this->audit('rch_encounter_completed', $encounter, $actor);

            return $this->closure->evaluate($visit, $actor);
        }, allowCompleted: true);
    }

    private function assertRchVisit(Visit $visit, User $actor): void
    {
        abort_unless($visit->facility_id === currentFacility()?->id && $actor->belongsToCurrentFacility()
            && $visit->patient?->facility_id === $visit->facility_id
            && $visit->destinationDepartment?->facility_id === $visit->facility_id
            && $visit->destinationDepartment->code === 'RCH', 404);
    }

    private function assertEncounter(RchEncounter $encounter, Visit $visit): void
    {
        $clinical = $encounter->clinicalEncounter;
        abort_unless($encounter->facility_id === $visit->facility_id
            && $encounter->visit_id === $visit->id
            && $encounter->patient_id === $visit->patient_id
            && $clinical?->encounter_type === ClinicalEncounterType::Rch
            && $clinical->facility_id === $visit->facility_id
            && $clinical->visit_id === $visit->id
            && $clinical->patient_id === $visit->patient_id
            && $clinical->department_id === $visit->destination_department_id, 404);
    }

    private function audit(string $event, RchEncounter $encounter, User $actor): void
    {
        ActivityLog::query()->create([
            'user_id' => $actor->id,
            'event' => $event,
            'subject_type' => $encounter::class,
            'subject_id' => $encounter->id,
        ]);
    }
}
