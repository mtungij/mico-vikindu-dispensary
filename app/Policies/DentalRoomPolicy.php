<?php

namespace App\Policies;

use App\Models\DentalRoom;
use App\Models\User;

class DentalRoomPolicy
{
    public function viewAny(User $user): bool { return $user->can('dental.manage-rooms'); }
    public function create(User $user): bool { return $user->can('dental.manage-rooms'); }
    public function update(User $user, DentalRoom $model): bool { return $user->can('dental.manage-rooms') && $model->facility_id === currentFacility()?->id; }
    public function delete(User $user, DentalRoom $model): bool { return $this->update($user, $model) && ! $model->chairs()->exists(); }
}
