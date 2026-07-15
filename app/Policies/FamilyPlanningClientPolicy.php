<?php

namespace App\Policies;

use App\Models\FamilyPlanningClient;
use App\Models\User;

class FamilyPlanningClientPolicy
{
    public function view(User $user, FamilyPlanningClient $model): bool { return $model->facility_id === currentFacility()?->id && $user->can('rch.family-planning.view'); }
    public function create(User $user): bool { return $user->can('rch.family-planning.register'); }
    public function update(User $user, FamilyPlanningClient $model): bool { return $model->facility_id === currentFacility()?->id && $user->can('rch.family-planning.register'); }
}
