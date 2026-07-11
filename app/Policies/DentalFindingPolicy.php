<?php

namespace App\Policies;

use App\Models\DentalToothFinding;
use App\Models\User;

class DentalFindingPolicy { public function create(User $u): bool { return $u->can('dental.add-finding'); } public function update(User $u, DentalToothFinding $m): bool { return $m->facility_id === currentFacility()?->id && $u->can('dental.edit-finding'); } public function markError(User $u, DentalToothFinding $m): bool { return $m->facility_id === currentFacility()?->id && $u->can('dental.mark-finding-error'); } }
