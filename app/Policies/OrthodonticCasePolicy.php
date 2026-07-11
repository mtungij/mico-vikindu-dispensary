<?php

namespace App\Policies;

use App\Models\OrthodonticCase;
use App\Models\User;

class OrthodonticCasePolicy { public function view(User $u, OrthodonticCase $m): bool { return $m->facility_id === currentFacility()?->id && $u->can('dental.manage-orthodontic-cases'); } public function create(User $u): bool { return $u->can('dental.manage-orthodontic-cases'); } public function update(User $u, OrthodonticCase $m): bool { return $this->view($u,$m); } }
