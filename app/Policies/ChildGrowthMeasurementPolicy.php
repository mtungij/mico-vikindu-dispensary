<?php

namespace App\Policies;

use App\Models\ChildGrowthMeasurement;
use App\Models\User;

class ChildGrowthMeasurementPolicy
{
    public function view(User $user, ChildGrowthMeasurement $model): bool { return $model->facility_id === currentFacility()?->id && $user->can('rch.growth.view'); }
    public function create(User $user): bool { return $user->can('rch.growth.record'); }
    public function amend(User $user, ChildGrowthMeasurement $model): bool { return $model->facility_id === currentFacility()?->id && $user->can('rch.growth.amend'); }
}
