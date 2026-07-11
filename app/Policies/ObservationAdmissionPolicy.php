<?php

namespace App\Policies;

use App\Models\ObservationAdmission;
use App\Models\User;

class ObservationAdmissionPolicy { public function viewAny(User $u): bool { return $u->can('observation.view-admission') || $u->can('observation.access'); } public function view(User $u, ObservationAdmission $m): bool { return $m->facility_id === currentFacility()?->id && $this->viewAny($u); } public function create(User $u): bool { return $u->can('observation.admit'); } public function update(User $u, ObservationAdmission $m): bool { return $m->facility_id === currentFacility()?->id && $u->can('observation.update-admission') && $m->isActive(); } public function discharge(User $u, ObservationAdmission $m): bool { return $m->facility_id === currentFacility()?->id && $u->can('observation.discharge') && $m->isActive(); } }
