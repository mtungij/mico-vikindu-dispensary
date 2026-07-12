<?php

namespace App\Policies;

use App\Models\DentalMaterial;
use App\Models\User;

class DentalMaterialPolicy
{
    public function viewAny(User $user): bool { return $user->can('dental.manage-materials') || $user->can('dental.use-materials'); }
    public function create(User $user): bool { return $user->can('dental.manage-materials'); }
    public function update(User $user, DentalMaterial $model): bool { return $user->can('dental.manage-materials') && $model->facility_id === currentFacility()?->id; }
    public function delete(User $user, DentalMaterial $model): bool { return $this->update($user, $model); }
}
