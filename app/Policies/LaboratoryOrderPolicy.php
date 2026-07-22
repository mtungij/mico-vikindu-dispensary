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

        return $user->can('laboratory-orders.create')
            && $user->can('opd.consult')
            && $this->sameFacility($user, $encounter)
            && $encounter->facility_id === currentFacility()?->id
            && $encounter->visit?->facility_id === $encounter->facility_id
            && $encounter->department?->code === 'OPD'
            && $encounter->encounter_type === ClinicalEncounterType::Opd
            && $encounter->visit?->current_department_id === $encounter->department_id
            && $encounter->started_at !== null
            && ! in_array($encounter->status, [ClinicalEncounterStatus::Completed, ClinicalEncounterStatus::Cancelled, ClinicalEncounterStatus::Referred], true)
            && ! in_array($encounter->visit?->visit_status, [VisitStatus::Completed, VisitStatus::Cancelled, VisitStatus::Referred, VisitStatus::Discharged], true);
    }

    public function cancel(User $user, LaboratoryOrder $model): bool { return $this->can($user, 'laboratory-orders.cancel', $model); }
}
