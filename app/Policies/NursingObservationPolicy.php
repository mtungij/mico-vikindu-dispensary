<?php

namespace App\Policies;

use App\Models\NursingObservation;
use App\Models\User;

class NursingObservationPolicy { public function create(User $u): bool { return $u->can('observation.record-nursing-observation'); } public function view(User $u, NursingObservation $m): bool { return $m->facility_id === currentFacility()?->id && $u->can('observation.view-admission'); } public function update(User $u, NursingObservation $m): bool { return $m->facility_id === currentFacility()?->id && $u->can('observation.amend-nursing-observation') && $m->status !== 'completed'; } }
