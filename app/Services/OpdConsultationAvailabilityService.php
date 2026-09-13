<?php

namespace App\Services;

use App\Enums\ClinicalEncounterStatus;
use App\Enums\ClinicalEncounterType;
use App\Enums\VisitStatus;
use App\Models\ClinicalEncounter;
use App\Models\Invoice;
use App\Models\Visit;
use Illuminate\Support\Facades\Route;

class OpdConsultationAvailabilityService
{
    public function __construct(private readonly VisitClosureService $closure) {}

    /** No writes: only the existing doctor-review path may reconstruct workflow. */
    public function resolve(Visit $visit, ?ClinicalEncounter $latest): array
    {
        $visit->loadMissing(['activeClinicalEncounter', 'currentDepartment', 'patient']);
        $state = $this->closure->workflowState($visit);
        $active = $visit->activeClinicalEncounter;
        $reason = $state['reason'];
        $terminal = in_array($visit->visit_status, [VisitStatus::Completed, VisitStatus::Cancelled, VisitStatus::Referred, VisitStatus::Discharged], true);

        if ($state['reason'] === 'doctor_review' && $state['status'] === VisitStatus::AwaitingDoctorReview
            && ! in_array($visit->visit_status, [VisitStatus::Cancelled, VisitStatus::Referred, VisitStatus::Discharged], true)) {
            return ['allowed' => true];
        }

        if ($terminal) {
            $reason = $visit->visit_status === VisitStatus::Completed ? 'completed' : 'closed';
        } elseif ($active && $active->department_id === $visit->current_department_id && $active->status->value !== 'paused' && $visit->currentDepartment?->code === 'OPD'
            && in_array($visit->visit_status, [VisitStatus::InProgress, VisitStatus::InQueue, VisitStatus::InConsultation, VisitStatus::AwaitingDepartment, VisitStatus::AwaitingPayment, VisitStatus::AwaitingLab, VisitStatus::AwaitingSample, VisitStatus::Processing, VisitStatus::AwaitingVerification, VisitStatus::ResultsReady, VisitStatus::AwaitingResults], true)
            && ! in_array($reason, ['admission', 'procedure'], true)) {
            // An ongoing consultation may order services without being finalized.
            return ['allowed' => true];
        } elseif (! $reason) {
            $reason = match ($visit->visit_status) {
                VisitStatus::AwaitingPayment => 'payment',
                VisitStatus::AwaitingPharmacy => 'pharmacy',
                VisitStatus::AwaitingLab, VisitStatus::AwaitingSample, VisitStatus::Processing,
                VisitStatus::AwaitingVerification, VisitStatus::ResultsReady, VisitStatus::AwaitingResults => 'laboratory',
                VisitStatus::AwaitingBed, VisitStatus::UnderObservation => 'admission',
                VisitStatus::AwaitingDoctorReview => 'conflict',
                default => null,
            };
            if (! $reason && $latest?->isTerminal()) {
                $reason = 'consultation_completed';
            }
            if (! $reason && $visit->currentDepartment?->code === 'OPD'
                && in_array($visit->visit_status, [VisitStatus::InProgress, VisitStatus::InQueue, VisitStatus::InConsultation, VisitStatus::AwaitingDepartment], true)
                && $visit->queues()->where('facility_id', $visit->facility_id)->where('department_id', $visit->current_department_id)
                    ->whereIn('queue_status', ['waiting', 'called', 'serving'])->exists()) {
                return ['allowed' => true];
            }
        }

        if ($reason === 'doctor_review') {
            $reason = 'laboratory'; // Results are not released yet.
        }
        $reason ??= 'conflict';
        $invoice = $reason === 'payment' ? Invoice::query()
            ->where('facility_id', $visit->facility_id)->where('patient_id', $visit->patient_id)
            ->where('visit_id', $visit->id)->where('balance_amount', '>', 0)
            ->whereNotIn('invoice_status', ['voided', 'cancelled'])->first() : null;
        $completed = $latest?->status === ClinicalEncounterStatus::Completed && ! $active;

        return [
            'allowed' => false,
            'show_history' => ! $active && $latest?->isTerminal() && $latest->department?->code === 'OPD'
                && in_array($reason, ['completed', 'closed', 'consultation_completed'], true),
            'reason_code' => $reason,
            'title' => __('opd_workflow.title'),
            'heading' => __($completed ? 'opd_workflow.consultation_completed' : 'opd_workflow.unavailable'),
            'message' => __('opd_workflow.messages.'.$reason),
            'completion_message' => $completed ? __($latest?->encounter_type === ClinicalEncounterType::FollowUp && $latest->department?->code === 'OPD' && $latest->parentEncounter?->status === ClinicalEncounterStatus::Completed && $latest->parentEncounter?->department?->code === 'OPD' ? 'opd_workflow.completed_follow_up' : 'opd_workflow.completed_care', ['patient' => $visit->patient->fullName()]) : null,
            'next_action' => __('opd_workflow.actions.'.$reason),
            'next_destination' => __('opd_workflow.destinations.'.$reason),
            'status_label' => __('opd_workflow.statuses.'.($reason === 'conflict' ? 'conflict' : $reason)),
            'after_payment' => $reason === 'payment' && $state['afterPayment']
                ? __('opd_workflow.destinations.'.$state['afterPayment']) : null,
            'balance' => $invoice ? ($invoice->currency === 'TZS' ? 'TSh' : $invoice->currency).' '.number_format((float) $invoice->balance_amount, 0) : null,
        ];
    }

    public function navigation(Visit $visit, string $reason, $actor): array
    {
        $actions = [];
        $destinations = [
            'payment' => ['billing.index', 'billing.view-queue', 'Nenda Billing'],
            'pharmacy' => ['pharmacy.index', 'pharmacy.view-queue', 'Nenda Pharmacy'],
            'laboratory' => ['laboratory.index', 'laboratory.view-queue', 'Nenda Laboratory'],
            'admission' => ['observation.index', 'observation.access', 'Nenda Observation'],
        ];
        if (isset($destinations[$reason])) {
            [$route, $permission, $label] = $destinations[$reason];
            if (Route::has($route) && $actor->can($permission)) {
                $actions[] = ['label' => $label, 'url' => route($route)];
            }
        }
        if (Route::has('opd.index') && $actor->can('opd.view-queue')) {
            $actions[] = ['label' => 'Rudi OPD Queue', 'url' => route('opd.index')];
        }
        if (Route::has('patients.show') && $actor->can('patients.view') && $actor->can('view', $visit->patient)) {
            $actions[] = ['label' => 'Angalia Patient / Clinical History', 'url' => route('patients.show', $visit->patient)];
        }

        return $actions;
    }
}
