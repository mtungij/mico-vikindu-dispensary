<?php

namespace App\Policies;

use App\Models\ObservationDeathRecord;
use App\Models\User;

class ObservationDeathRecordPolicy { public function create(User $u): bool { return $u->can('observation.record-death'); } public function view(User $u, ObservationDeathRecord $m): bool { return $m->facility_id === currentFacility()?->id && $u->can('observation.record-death'); } }
