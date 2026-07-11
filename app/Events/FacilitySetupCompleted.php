<?php

namespace App\Events;

use App\Models\Facility;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class FacilitySetupCompleted
{
    use Dispatchable;

    public function __construct(public Facility $facility, public User $user) {}
}
