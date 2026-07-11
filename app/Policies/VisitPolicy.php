<?php

namespace App\Policies;

use App\Models\Visit;
use App\Models\User;

class VisitPolicy
{
    public function view(User $user, Visit $visit): bool { return $user->can('reception.access') && $visit->facility_id === currentFacility()?->id; }
    public function create(User $user): bool { return $user->can('reception.open-visit'); }
    public function update(User $user, Visit $visit): bool { return $user->can('reception.update-visit') && $visit->facility_id === currentFacility()?->id; }
    public function cancel(User $user, Visit $visit): bool { return $user->can('reception.cancel-visit') && $visit->facility_id === currentFacility()?->id; }
    public function transfer(User $user, Visit $visit): bool { return $user->can('reception.transfer-patient') && $visit->facility_id === currentFacility()?->id; }
}
