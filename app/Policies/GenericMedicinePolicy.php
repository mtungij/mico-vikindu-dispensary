<?php

namespace App\Policies;

use App\Models\GenericMedicine;
use App\Models\User;

class GenericMedicinePolicy
{
    public function viewAny(User $user): bool { return $user->can('pharmacy.manage-generics'); }
    public function create(User $user): bool { return $user->can('pharmacy.manage-generics'); }
    public function update(User $user, GenericMedicine $model): bool { return $user->can('pharmacy.manage-generics') && $model->facility_id === currentFacility()?->id; }
    public function delete(User $user, GenericMedicine $model): bool { return $this->update($user, $model); }
}
