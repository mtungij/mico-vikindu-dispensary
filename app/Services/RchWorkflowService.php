<?php

namespace App\Services;

use App\Enums\ClinicalEncounterStatus;
use App\Enums\ClinicalEncounterType;
use App\Enums\PrescriptionStatus;
use App\Enums\RchService;
use App\Enums\VisitStatus;
use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\ClinicalEncounter;
use App\Models\ClinicalProcedureOrder;
use App\Models\Department;
use App\Models\Prescription;
use App\Models\RchEncounter;
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
            $legacy = $service?->code === 'RCHCON' && $service->facility_id === $visit->facility_id
                && $service->department_id === $visit->destination_department_id;
            $invoice = $visit->invoice;
            $charge = $invoice?->items()->where('service_id', $service?->id)
                ->where('metadata->charge_source', $legacy ? 'reception_consultation' : 'reception_rch_service')
                ->whereNotIn('status', ['cancelled', 'reversed', 'non_billable'])->first();

            if ((! $definition && ! $legacy) || ! $charge || (float) $charge->paid_amount < (float) $charge->patient_amount
                || $invoice->facility_id !== $visit->facility_id || $invoice->patient_id !== $visit->patient_id
                || in_array($invoice->status, ['cancelled', 'void', 'voided', 'refunded', 'reversed', 'replaced'], true)
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

    public function withOpenEncounter(RchEncounter $encounter, User $actor, callable $action): mixed
    {
        return DB::transaction(function () use ($encounter, $actor, $action): mixed {
            $visit = Visit::query()->lockForUpdate()->findOrFail($encounter->visit_id);
            $this->assertRchVisit($visit, $actor);
            $encounter = RchEncounter::query()->forCurrentFacility()->lockForUpdate()->findOrFail($encounter->id);
            $this->assertEncounter($encounter, $visit);
            abort_if(in_array($visit->visit_status, [VisitStatus::Completed, VisitStatus::Cancelled, VisitStatus::Referred, VisitStatus::Discharged], true), 403);
            Gate::forUser($actor)->authorize('update', $encounter);
            abort_if($encounter->clinicalEncounter->isReadOnly(), 403);

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

    public function orderUltrasound(RchEncounter $encounter, Visit $visit, User $actor, ?string $instructions): ClinicalProcedureOrder
    {
        $clinical = $encounter->clinicalEncounter;
        Gate::forUser($actor)->authorize('create', ClinicalProcedureOrder::class);
        $service = app(ReceptionChargeService::class)->rchServices($visit->destinationDepartment)
            ->where('code', RchService::Ultrasound->value)->firstOrFail();
        $existing = $clinical->procedureOrders()->where('service_id', $service->id)->first();
        if ($existing) {
            return $existing;
        }

        $receptionCharge = $visit->invoice?->items()->where('service_id', $service->id)
            ->where('metadata->charge_source', 'reception_rch_service')->first();
        if (! $receptionCharge) {
            app(ReceptionChargeService::class)->resolveConsultationPrice(
                $service, $visit->payer_type, $visit->payerProfile?->insurance_provider_id, $visit->payerProfile?->corporate_account_id,
            );
        }
        $order = app(ProcedureOrderService::class)->createOrder($clinical, [
            'service_id' => $service->id,
            'instructions' => $instructions,
        ], $actor, $receptionCharge);
        if ($order->status->value === 'awaiting_payment') {
            $department = Department::query()->where('facility_id', $visit->facility_id)->where('code', 'BIL')->where('is_active', true)->firstOrFail();
            $this->workflow->createQueue($visit, $department, $actor, VisitStatus::AwaitingPayment, 'Ultrasound payment required', true, false);
            app(ProcedureOrderService::class)->releasePaidInvoice($visit->invoice, $actor);
        } elseif ($service->department?->queue_enabled && $service->department_id !== $clinical->department_id) {
            $this->workflow->createQueue($visit, $service->department, $actor, VisitStatus::AwaitingDepartment, 'Ultrasound ordered', true, false);
        }

        return $order;
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

    public function completeEncounter(RchEncounter $encounter, User $actor, array $notes): void
    {
        $this->withOpenEncounter($encounter, $actor, function (RchEncounter $encounter, Visit $visit) use ($actor, $notes): void {
            Gate::forUser($actor)->authorize('complete', $encounter);
            $clinical = $encounter->clinicalEncounter;
            $blockers = $this->closure->workflowState($visit)['blockers'];
            if ($blockers || $clinical->laboratoryOrders()->whereNotIn('status', ['completed', 'cancelled'])->exists()
                || $this->closure->pendingReviewResults($visit)
                    ->whereHas('orderItem', fn ($item) => $item->whereNotIn('status', ['cancelled', 'not_performed', 'entered_in_error']))->exists()
                || ($encounter->encounter_type === 'ultrasound' && $clinical->procedureOrders()->doesntExist())) {
                throw ValidationException::withMessages(['encounter' => 'Resolve pending charges, tests, result reviews, prescriptions and procedures before completing the visit.']);
            }

            $completedAt = now();
            $encounter->update([
                ...$notes,
                'status' => 'completed',
                'completed_at' => $completedAt,
                'signed_off_by' => $actor->id,
                'signed_off_at' => $completedAt,
                'updated_by' => $actor->id,
            ]);
            $clinical->update([
                'chief_complaint' => $encounter->chief_complaint,
                'clinical_summary' => $encounter->clinical_summary,
                'treatment_plan' => $encounter->treatment_plan,
                'follow_up_required' => $encounter->follow_up_required,
                'follow_up_date' => $encounter->follow_up_date,
                'status' => ClinicalEncounterStatus::Completed,
                'completed_at' => $completedAt,
                'completed_by' => $actor->id,
                'signed_off_by' => $actor->id,
                'signed_off_at' => $completedAt,
                'updated_by' => $actor->id,
            ]);
            $this->workflow->completeVisit($visit, $actor, 'RCH encounter and downstream services completed');
            $this->audit('rch_encounter_completed', $encounter, $actor);
        });
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
