<?php

namespace App\Policies;

use App\Models\Pregnancy;
use App\Models\User;

class PregnancyPolicy
{
    public function view(User $user, Pregnancy $model): bool { return $model->facility_id === currentFacility()?->id && $user->can('rch.pregnancies.view'); }
    public function create(User $user): bool { return $user->can('rch.pregnancies.create'); }
    public function update(User $user, Pregnancy $model): bool { return $model->facility_id === currentFacility()?->id && $model->status === 'active' && $user->can('rch.pregnancies.update'); }
    public function amend(User $user, Pregnancy $model): bool { return $model->facility_id === currentFacility()?->id && $user->can('rch.pregnancies.amend'); }
    public function close(User $user, Pregnancy $model): bool { return $model->facility_id === currentFacility()?->id && $user->can('rch.pregnancies.close'); }
}
