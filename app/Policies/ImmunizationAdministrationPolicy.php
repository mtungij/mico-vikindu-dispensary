<?php

namespace App\Policies;

use App\Models\ImmunizationAdministration;
use App\Models\User;

class ImmunizationAdministrationPolicy
{
    public function view(User $user, ImmunizationAdministration $model): bool { return $model->facility_id === currentFacility()?->id && $user->can('rch.immunization.view'); }
    public function create(User $user): bool { return $user->can('rch.immunization.administer'); }
    public function amend(User $user, ImmunizationAdministration $model): bool { return $model->facility_id === currentFacility()?->id && $user->can('rch.immunization.amend'); }
}
