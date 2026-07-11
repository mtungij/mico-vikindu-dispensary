<?php

namespace App\Policies;

use App\Models\DentalTreatmentPlan;
use App\Models\User;

class DentalTreatmentPlanPolicy { public function view(User $u, DentalTreatmentPlan $m): bool { return $m->facility_id === currentFacility()?->id && $u->can('dental.view-history'); } public function create(User $u): bool { return $u->can('dental.create-treatment-plan'); } public function update(User $u, DentalTreatmentPlan $m): bool { return $m->facility_id === currentFacility()?->id && $u->can('dental.update-treatment-plan'); } public function approve(User $u, DentalTreatmentPlan $m): bool { return $m->facility_id === currentFacility()?->id && $u->can('dental.approve-treatment-plan'); } public function print(User $u, DentalTreatmentPlan $m): bool { return $m->facility_id === currentFacility()?->id && $u->can('dental.print-treatment-plan'); } }
