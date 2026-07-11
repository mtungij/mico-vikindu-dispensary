<?php

namespace App\Policies;

use App\Models\NursingHandover;
use App\Models\User;

class NursingHandoverPolicy { public function create(User $u): bool { return $u->can('observation.create-handover'); } public function acknowledge(User $u, NursingHandover $m): bool { return $m->facility_id === currentFacility()?->id && $u->can('observation.acknowledge-handover') && ! $m->acknowledged_at; } }
