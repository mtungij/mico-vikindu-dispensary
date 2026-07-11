<?php

namespace App\Policies;

use App\Models\LaboratoryTest;
use App\Models\User;

class LaboratoryTestPolicy
{
    public function viewAny(User $user): bool { return $user->can('laboratory.manage-tests'); }
    public function view(User $user, LaboratoryTest $model): bool { return $user->can('laboratory.manage-tests') && $model->facility_id === currentFacility()?->id; }
    public function manage(User $user, LaboratoryTest $model): bool { return $this->view($user, $model); }
}
