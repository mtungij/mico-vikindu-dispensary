<?php

namespace App\Policies;

use App\Models\DentalToothRecord;
use App\Models\User;

class DentalToothRecordPolicy { public function view(User $u, DentalToothRecord $m): bool { return $m->facility_id === currentFacility()?->id && $u->can('dental.manage-odontogram'); } public function update(User $u, DentalToothRecord $m): bool { return $this->view($u,$m); } }
