<?php

namespace App\Policies;

use App\Models\ObservationOrder;
use App\Models\User;

class ObservationOrderPolicy { public function create(User $u): bool { return $u->can('observation.create-order'); } public function update(User $u, ObservationOrder $m): bool { return $m->facility_id === currentFacility()?->id && ! in_array($m->status?->value ?? $m->status, ['completed','cancelled'], true); } public function complete(User $u, ObservationOrder $m): bool { return $m->facility_id === currentFacility()?->id && $u->can('observation.complete-order'); } public function cancel(User $u, ObservationOrder $m): bool { return $m->facility_id === currentFacility()?->id && $u->can('observation.cancel-order'); } }
