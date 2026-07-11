<?php

namespace App\Policies;

use App\Models\StaffSignature;
use App\Models\User;

class StaffSignaturePolicy
{
    public function view(User $user, StaffSignature $signature): bool
    {
        return $user->can('staff.view') && $signature->facility_id === currentFacility()?->id;
    }
}
