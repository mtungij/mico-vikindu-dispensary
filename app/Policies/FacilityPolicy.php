<?php

namespace App\Policies;

use App\Models\Facility;
use App\Models\User;

class FacilityPolicy
{
    public function setupFacility(User $user): bool
    {
        return $user->is_super_admin;
    }

    public function updateFacility(User $user, ?Facility $facility = null): bool
    {
        return $user->is_super_admin;
    }

    public function completeFacilitySetup(User $user, ?Facility $facility = null): bool
    {
        return $user->is_super_admin;
    }
}
