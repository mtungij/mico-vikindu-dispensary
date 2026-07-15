<?php

namespace App\Policies;

use App\Models\FamilyPlanningVisit;
use App\Models\User;

class FamilyPlanningVisitPolicy
{
    public function view(User $user, FamilyPlanningVisit $model): bool { return $model->facility_id === currentFacility()?->id && $user->can('rch.family-planning.view'); }
    public function create(User $user): bool { return $user->can('rch.family-planning.record-visit'); }
    public function update(User $user, FamilyPlanningVisit $model): bool { return $model->facility_id === currentFacility()?->id && $model->status !== 'completed' && $user->can('rch.family-planning.record-visit'); }
}
