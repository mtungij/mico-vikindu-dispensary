<?php

namespace App\Policies;

use App\Models\ChildNutritionAssessment;
use App\Models\User;

class ChildNutritionAssessmentPolicy
{
    public function view(User $user, ChildNutritionAssessment $model): bool { return $model->facility_id === currentFacility()?->id && $user->can('rch.growth.view'); }
    public function create(User $user): bool { return $user->can('rch.growth.assess-nutrition'); }
}
