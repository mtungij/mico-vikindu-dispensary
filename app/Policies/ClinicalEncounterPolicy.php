<?php

namespace App\Policies;

use App\Enums\ClinicalEncounterStatus;
use App\Models\ClinicalEncounter;
use App\Models\User;
use App\Policies\Concerns\ChecksClinicalAccess;

class ClinicalEncounterPolicy
{
    use ChecksClinicalAccess;
    public function view(User $user, ClinicalEncounter $model): bool { return $this->can($user, 'clinical-encounters.view', $model); }
    public function create(User $user): bool { return $user->can('clinical-encounters.create'); }
    public function update(User $user, ClinicalEncounter $model): bool { return $this->can($user, in_array($model->status, [ClinicalEncounterStatus::Completed, ClinicalEncounterStatus::Cancelled, ClinicalEncounterStatus::Referred], true) ? 'clinical-encounters.amend' : 'clinical-encounters.update-draft', $model); }
    public function complete(User $user, ClinicalEncounter $model): bool { return $this->can($user, 'clinical-encounters.complete', $model); }
    public function print(User $user, ClinicalEncounter $model): bool { return $this->can($user, 'clinical-encounters.print', $model); }
}
