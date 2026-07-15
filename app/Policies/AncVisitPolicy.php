<?php

namespace App\Policies;

use App\Models\AncVisit;
use App\Models\User;

class AncVisitPolicy
{
    public function view(User $user, AncVisit $model): bool { return $model->facility_id === currentFacility()?->id && $user->can('rch.anc.view'); }
    public function create(User $user): bool { return $user->can('rch.anc.record-visit'); }
    public function update(User $user, AncVisit $model): bool { return $model->facility_id === currentFacility()?->id && $model->status !== 'completed' && $user->can('rch.anc.record-visit'); }
    public function amend(User $user, AncVisit $model): bool { return $model->facility_id === currentFacility()?->id && $user->can('rch.anc.amend-visit'); }
    public function print(User $user, AncVisit $model): bool { return $model->facility_id === currentFacility()?->id && $user->can('rch.anc.print'); }
}
