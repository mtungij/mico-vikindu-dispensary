<?php

namespace App\Policies;

use App\Models\LaboratoryTestCategory;
use App\Models\User;

class LaboratoryTestCategoryPolicy
{
    public function viewAny(User $user): bool { return $user->can('laboratory.manage-test-categories'); }
    public function manage(User $user, LaboratoryTestCategory $model): bool { return $user->can('laboratory.manage-test-categories') && $model->facility_id === currentFacility()?->id; }
}
