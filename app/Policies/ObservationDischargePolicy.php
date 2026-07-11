<?php

namespace App\Policies;

use App\Models\ObservationDischarge;
use App\Models\User;

class ObservationDischargePolicy { public function view(User $u, ObservationDischarge $m): bool { return $m->facility_id === currentFacility()?->id && $u->can('observation.view-admission'); } public function create(User $u): bool { return $u->can('observation.discharge'); } public function print(User $u, ObservationDischarge $m): bool { return $m->facility_id === currentFacility()?->id && $u->can('observation.print-discharge'); } }
