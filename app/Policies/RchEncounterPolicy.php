<?php

namespace App\Policies;

use App\Models\RchEncounter;
use App\Models\User;

class RchEncounterPolicy
{
    public function view(User $user, RchEncounter $model): bool { return $model->facility_id === currentFacility()?->id && $user->can('rch.view-history'); }
    public function create(User $user): bool { return $user->can('rch.start-encounter'); }
    public function update(User $user, RchEncounter $model): bool { return $model->facility_id === currentFacility()?->id && $model->status !== 'completed' && $user->can('rch.start-encounter'); }
    public function complete(User $user, RchEncounter $model): bool { return $model->facility_id === currentFacility()?->id && $user->can('rch.complete-encounter'); }
}
