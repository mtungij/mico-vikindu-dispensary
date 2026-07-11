<?php

namespace App\Policies;

use App\Models\SpecimenType;
use App\Models\User;

class SpecimenTypePolicy
{
    public function viewAny(User $user): bool { return $user->can('laboratory.manage-specimens'); }
    public function manage(User $user, SpecimenType $model): bool { return $user->can('laboratory.manage-specimens') && $model->facility_id === currentFacility()?->id; }
}
