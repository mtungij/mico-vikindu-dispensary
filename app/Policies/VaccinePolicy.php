<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vaccine;

class VaccinePolicy
{
    public function view(User $user, Vaccine $model): bool { return ($model->facility_id === null || $model->facility_id === currentFacility()?->id) && $user->can('rch.immunization.view'); }
    public function manage(User $user): bool { return $user->can('rch.vaccines.manage'); }
}
