<?php

namespace App\Policies;

use App\Models\ObservationRoom;
use App\Models\User;

class ObservationRoomPolicy { public function viewAny(User $u): bool { return $u->can('observation.manage-rooms') || $u->can('observation.view-bed-board'); } public function view(User $u, ObservationRoom $m): bool { return $m->facility_id === currentFacility()?->id && $this->viewAny($u); } public function create(User $u): bool { return $u->can('observation.manage-rooms'); } public function update(User $u, ObservationRoom $m): bool { return $m->facility_id === currentFacility()?->id && $u->can('observation.manage-rooms'); } public function delete(User $u, ObservationRoom $m): bool { return $this->update($u,$m) && ! $m->activeAdmissions()->exists(); } }
