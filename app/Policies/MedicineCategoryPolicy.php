<?php

namespace App\Policies;

use App\Models\MedicineCategory;
use App\Models\User;

class MedicineCategoryPolicy
{
    public function viewAny(User $user): bool { return $user->can('pharmacy.manage-medicine-categories'); }
    public function create(User $user): bool { return $user->can('pharmacy.manage-medicine-categories'); }
    public function update(User $user, MedicineCategory $model): bool { return $user->can('pharmacy.manage-medicine-categories') && $model->facility_id === currentFacility()?->id; }
    public function delete(User $user, MedicineCategory $model): bool { return $this->update($user, $model); }
}
