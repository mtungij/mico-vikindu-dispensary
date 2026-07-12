<?php

namespace App\Policies;

use App\Models\User;

class InsuranceMembershipPolicy
{
    public function viewAny(User $user): bool { return $user->can('insurance.view-memberships'); }
    public function view(User $user, mixed $model): bool { return $model->facility_id === currentFacility()?->id && $user->can('insurance.view-memberships'); }
    public function create(User $user): bool { return $user->can('insurance.manage-memberships'); }
    public function update(User $user, mixed $model): bool { return $model->facility_id === currentFacility()?->id && $user->can('insurance.manage-memberships'); }
    public function verify(User $user, mixed $model): bool { return $model->facility_id === currentFacility()?->id && $user->can('insurance.verify-membership'); }
    public function override(User $user, mixed $model): bool { return $model->facility_id === currentFacility()?->id && $user->can('insurance.override-verification'); }
}
