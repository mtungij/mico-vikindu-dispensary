<?php

namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;

class SupplierPolicy
{
    public function viewAny(User $user): bool { return $user->can('pharmacy.manage-suppliers'); }
    public function create(User $user): bool { return $user->can('pharmacy.manage-suppliers'); }
    public function update(User $user, Supplier $model): bool { return $user->can('pharmacy.manage-suppliers') && $model->facility_id === currentFacility()?->id; }
    public function delete(User $user, Supplier $model): bool { return $this->update($user, $model); }
}
