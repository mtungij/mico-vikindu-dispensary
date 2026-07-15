<?php

namespace App\Policies;

use App\Models\ImmunizationSchedule;
use App\Models\User;

class ImmunizationSchedulePolicy
{
    public function view(User $user, ImmunizationSchedule $model): bool { return $model->facility_id === currentFacility()?->id && $user->can('rch.immunization.view'); }
    public function manage(User $user, ImmunizationSchedule $model): bool { return $model->facility_id === currentFacility()?->id && $user->can('rch.immunization.manage-schedules'); }
}
