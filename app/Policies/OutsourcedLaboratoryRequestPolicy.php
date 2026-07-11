<?php

namespace App\Policies;

use App\Models\OutsourcedLaboratoryRequest;
use App\Models\User;

class OutsourcedLaboratoryRequestPolicy
{
    public function manage(User $user, OutsourcedLaboratoryRequest $model): bool { return $user->can('laboratory-outsourced-tests.manage') && $model->facility_id === currentFacility()?->id; }
}
