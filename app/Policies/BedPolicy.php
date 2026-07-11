<?php

namespace App\Policies;

use App\Models\Bed;
use App\Models\User;

class BedPolicy { public function viewAny(User $u): bool { return $u->can('observation.view-bed-board') || $u->can('observation.manage-beds'); } public function view(User $u, Bed $m): bool { return $m->facility_id === currentFacility()?->id && $this->viewAny($u); } public function create(User $u): bool { return $u->can('observation.manage-beds'); } public function update(User $u, Bed $m): bool { return $m->facility_id === currentFacility()?->id && $u->can('observation.manage-beds'); } public function delete(User $u, Bed $m): bool { return $this->update($u,$m) && ($m->status?->value ?? $m->status) !== 'occupied'; } }
