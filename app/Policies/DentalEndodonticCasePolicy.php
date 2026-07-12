<?php

namespace App\Policies;

use App\Models\DentalEndodonticCase;
use App\Models\User;

class DentalEndodonticCasePolicy
{
    public function view(User $user, DentalEndodonticCase $model): bool { return $user->can('dental.manage-endodontic-cases') && $model->facility_id === currentFacility()?->id; }
    public function create(User $user): bool { return $user->can('dental.manage-endodontic-cases'); }
    public function update(User $user, DentalEndodonticCase $model): bool { return $this->view($user, $model); }
}
