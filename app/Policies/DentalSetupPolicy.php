<?php

namespace App\Policies;

use App\Models\User;

class DentalSetupPolicy
{
    public function viewAny(User $user): bool { return $user->can('dental.manage-settings'); }
    public function create(User $user): bool { return $user->can('dental.manage-settings'); }
    public function update(User $user, object $model): bool { return $user->can('dental.manage-settings') && (($model->facility_id ?? currentFacility()?->id) === null || ($model->facility_id ?? null) === currentFacility()?->id); }
    public function delete(User $user, object $model): bool { return $this->update($user, $model) && ! ($model->is_system ?? false); }
}
