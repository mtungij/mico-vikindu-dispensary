<?php

namespace App\Policies;

use App\Models\LaboratoryCriticalResultNotification;
use App\Models\User;

class LaboratoryCriticalResultPolicy
{
    public function view(User $user, LaboratoryCriticalResultNotification $model): bool { return $user->can('laboratory-critical-results.view') && $model->facility_id === currentFacility()?->id; }
}
