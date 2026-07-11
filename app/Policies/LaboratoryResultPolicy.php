<?php

namespace App\Policies;

use App\Enums\LaboratoryResultStatus;
use App\Models\LaboratoryResult;
use App\Models\User;

class LaboratoryResultPolicy
{
    public function view(User $user, LaboratoryResult $model): bool { return $user->can('laboratory-results.view') && $model->facility_id === currentFacility()?->id; }
    public function enter(User $user): bool { return $user->can('laboratory-results.enter'); }
    public function verify(User $user, LaboratoryResult $model): bool { return $user->can('laboratory-results.verify') && $model->facility_id === currentFacility()?->id; }
    public function release(User $user, LaboratoryResult $model): bool { return $user->can('laboratory-results.release') && $model->facility_id === currentFacility()?->id && $model->result_status === LaboratoryResultStatus::Verified; }
    public function print(User $user, LaboratoryResult $model): bool { return $user->can('laboratory-results.print') && $model->facility_id === currentFacility()?->id; }
    public function amend(User $user, LaboratoryResult $model): bool { return $user->can('laboratory-results.amend') && $model->facility_id === currentFacility()?->id; }
}
