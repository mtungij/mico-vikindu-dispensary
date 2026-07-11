<?php

namespace App\Policies;

use App\Models\LaboratorySample;
use App\Models\User;

class LaboratorySamplePolicy
{
    public function view(User $user, LaboratorySample $model): bool { return $user->can('laboratory.view-order') && $model->facility_id === currentFacility()?->id; }
    public function collect(User $user): bool { return $user->can('laboratory.collect-sample'); }
    public function reject(User $user, LaboratorySample $model): bool { return $user->can('laboratory.reject-sample') && $model->facility_id === currentFacility()?->id; }
}
