<?php

namespace App\Policies;

use App\Models\PregnancyRiskFactor;
use App\Models\User;

class PregnancyRiskFactorPolicy
{
    public function view(User $user, PregnancyRiskFactor $model): bool { return $model->facility_id === currentFacility()?->id && $user->can('rch.pregnancies.view'); }
    public function manage(User $user, PregnancyRiskFactor $model): bool { return $model->facility_id === currentFacility()?->id && $user->can('rch.pregnancies.manage-risk'); }
}
