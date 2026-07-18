<?php

namespace App\Policies;

use App\Enums\ClinicalEncounterStatus;
use App\Enums\ClinicalEncounterType;
use App\Models\ClinicalEncounter;
use App\Models\User;
use App\Policies\Concerns\ChecksClinicalAccess;

class ClinicalEncounterPolicy
{
    use ChecksClinicalAccess;
    public function view(User $user, ClinicalEncounter $model): bool { return $this->canEncounter($user, ['clinical-encounters.view', 'opd.consult'], $model); }
    public function create(User $user): bool { return $user->can('clinical-encounters.create'); }
    public function update(User $user, ClinicalEncounter $model): bool { return $this->canEncounter($user, in_array($model->status, [ClinicalEncounterStatus::Completed, ClinicalEncounterStatus::Cancelled, ClinicalEncounterStatus::Referred], true) ? ['clinical-encounters.amend'] : ['clinical-encounters.update-draft', 'opd.consult'], $model); }
    public function complete(User $user, ClinicalEncounter $model): bool { return $this->canEncounter($user, ['clinical-encounters.complete', 'opd.complete-consultation'], $model); }
    public function print(User $user, ClinicalEncounter $model): bool { return $this->can($user, 'clinical-encounters.print', $model); }

    private function canEncounter(User $user, array $permissions, ClinicalEncounter $model): bool
    {
        if (! $this->sameFacility($user, $model)) {
            return false;
        }

        foreach ($permissions as $permission) {
            if ($permission !== 'opd.consult' && $permission !== 'opd.complete-consultation' && $user->can($permission)) {
                return true;
            }

            if ($model->encounter_type === ClinicalEncounterType::Opd && $user->can($permission)) {
                return true;
            }
        }

        return false;
    }
}
