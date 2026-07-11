<?php

namespace App\Policies;

use App\Models\MedicationAdministration;
use App\Models\User;

class MedicationAdministrationPolicy { public function create(User $u): bool { return $u->can('observation.administer-medication'); } public function update(User $u, MedicationAdministration $m): bool { return $m->facility_id === currentFacility()?->id && $u->can('observation.administer-medication') && ($m->administration_status?->value ?? $m->administration_status) !== 'administered'; } }
