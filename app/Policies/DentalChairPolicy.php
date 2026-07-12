<?php

namespace App\Policies;

use App\Models\DentalChair;
use App\Models\User;

class DentalChairPolicy
{
    public function viewAny(User $user): bool { return $user->can('dental.manage-chairs'); }
    public function create(User $user): bool { return $user->can('dental.manage-chairs'); }
    public function update(User $user, DentalChair $model): bool { return $user->can('dental.manage-chairs') && $model->facility_id === currentFacility()?->id; }
    public function delete(User $user, DentalChair $model): bool { return $this->update($user, $model) && $model->status !== 'occupied'; }
}
