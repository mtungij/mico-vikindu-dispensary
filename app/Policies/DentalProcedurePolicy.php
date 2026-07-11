<?php

namespace App\Policies;

use App\Models\DentalProcedure;
use App\Models\User;

class DentalProcedurePolicy { public function view(User $u, DentalProcedure $m): bool { return $m->facility_id === currentFacility()?->id && $u->can('dental.view-history'); } public function create(User $u): bool { return $u->can('dental.perform-preventive-care') || $u->can('dental.perform-restorative-treatment') || $u->can('dental.perform-oral-surgery'); } public function update(User $u, DentalProcedure $m): bool { return $m->facility_id === currentFacility()?->id && $u->can('dental.amend-procedure'); } public function print(User $u, DentalProcedure $m): bool { return $m->facility_id === currentFacility()?->id && $u->can('dental.print-procedure-report'); } }
