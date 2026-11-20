<?php

namespace App\Policies;

use App\Enums\ClinicalEncounterStatus;
use App\Enums\ClinicalEncounterType;
use App\Enums\VisitStatus;
use App\Models\ClinicalEncounter;
use App\Models\LaboratoryOrder;
use App\Models\User;
use App\Policies\Concerns\ChecksClinicalAccess;
use Illuminate\Support\Facades\Gate;

class LaboratoryOrderPolicy
{
    use ChecksClinicalAccess;

    public function view(User $user, LaboratoryOrder $model): bool
    {
        if (! $this->can($user, 'laboratory-orders.view', $model)) {
            return false;
        }

        if ($user->can('laboratory.view-order') || $model->ordered_by === $user->id) {
            return true;
        }

        return $model->encounter !== null
            && Gate::forUser($user)->allows('view', $model->encounter);
    }

    public function create(User $user, ClinicalEncounter $encounter): bool
    {
        $encounter->loadMissing(['department', 'visit']);

        $rch = $encounter->encounter_type === ClinicalEncounterType::Rch
            && $encounter->department?->code === 'RCH'
            && $encounter->visit?->destination_department_id === $encounter->department_id
            && $user->can('rch.start-encounter');
        $opd = $encounter->encounter_type === ClinicalEncounterType::Opd
            && $encounter->department?->code === 'OPD'
            && $encounter->visit?->current_department_id === $encounter->department_id
            && $user->can('opd.consult');

        return $user->can('laboratory-orders.create')
            && ($rch || $opd)
            && $this->sameFacility($user, $encounter)
            && $encounter->facility_id === currentFacility()?->id
            && $encounter->visit?->facility_id === $encounter->facility_id
            && $encounter->visit?->patient_id === $encounter->patient_id
            && $encounter->started_at !== null
            && ! in_array($encounter->status, [ClinicalEncounterStatus::Completed, ClinicalEncounterStatus::Cancelled, ClinicalEncounterStatus::Referred], true)
            && ! in_array($encounter->visit?->visit_status, [VisitStatus::Completed, VisitStatus::Cancelled, VisitStatus::Referred, VisitStatus::Discharged], true);
    }

    public function viewReport(User $user, LaboratoryOrder $model): bool
    {
        return $this->canAccessReport($user, $model, 'laboratory-results.view');
    }

    public function downloadReport(User $user, LaboratoryOrder $model): bool
    {
        return $this->canAccessReport($user, $model, 'laboratory-results.download');
    }

    public function printReport(User $user, LaboratoryOrder $model): bool
    {
        return $this->canAccessReport($user, $model, 'laboratory-results.print');
    }

    public function decideItem(User $user, LaboratoryOrder $model): bool
    {
        return $this->can($user, 'laboratory-results.enter', $model)
            || ($model->source === LaboratoryOrder::SOURCE_RCH
                && $model->encounter?->encounter_type === ClinicalEncounterType::Rch
                && $user->can('rch.start-encounter')
                && $this->can($user, 'laboratory-orders.cancel', $model));
    }

    public function cancel(User $user, LaboratoryOrder $model): bool
    {
        return $this->can($user, 'laboratory-orders.cancel', $model);
    }

    private function canAccessReport(User $user, LaboratoryOrder $model, string $permission): bool
    {
        $model->loadMissing('visit:id,facility_id,patient_id');

        return $this->can($user, $permission, $model)
            && $model->facility_id === currentFacility()?->id
            && $model->visit?->facility_id === $model->facility_id
            && $model->visit?->patient_id === $model->patient_id;
    }
}
