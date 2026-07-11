<?php

namespace App\Policies;

use App\Models\DentalEncounter;
use App\Models\User;

class DentalEncounterPolicy
{
    public function viewAny(User $user): bool { return $user->can('dental.view-queue') || $user->can('dental.consult'); }
    public function view(User $user, DentalEncounter $model): bool { return $model->facility_id === currentFacility()?->id && $user->can('dental.view-history'); }
    public function create(User $user): bool { return $user->can('dental.start-consultation'); }
    public function update(User $user, DentalEncounter $model): bool { return $model->facility_id === currentFacility()?->id && $user->can('dental.consult') && ! $model->isCompleted(); }
    public function complete(User $user, DentalEncounter $model): bool { return $model->facility_id === currentFacility()?->id && $user->can('dental.complete-consultation'); }
    public function print(User $user, DentalEncounter $model): bool { return $model->facility_id === currentFacility()?->id && $user->can('dental.print-chart'); }
}
