<?php

namespace App\Policies;

use App\Models\BedsideProcedure;
use App\Models\User;

class BedsideProcedurePolicy { public function create(User $u): bool { return $u->can('observation.record-procedure'); } public function update(User $u, BedsideProcedure $m): bool { return $m->facility_id === currentFacility()?->id && $u->can('observation.record-procedure') && $m->status !== 'completed'; } }
