<?php

namespace App\Policies;

use App\Models\DentalLabOrder;
use App\Models\User;

class DentalLabOrderPolicy { public function view(User $u, DentalLabOrder $m): bool { return $m->facility_id === currentFacility()?->id && $u->can('dental.manage-dental-lab-orders'); } public function create(User $u): bool { return $u->can('dental.manage-dental-lab-orders'); } public function update(User $u, DentalLabOrder $m): bool { return $this->view($u,$m); } }
