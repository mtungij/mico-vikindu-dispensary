<?php

namespace App\Policies;

use App\Models\Medicine;
use App\Models\User;

class MedicinePolicy
{
    public function viewAny(User $user): bool { return $user->can('pharmacy.manage-medicines') || $user->can('pharmacy.view-stock'); }
    public function view(User $user, Medicine $model): bool { return $this->viewAny($user) && $model->facility_id === currentFacility()?->id; }
    public function create(User $user): bool { return $user->can('pharmacy.manage-medicines'); }
    public function update(User $user, Medicine $model): bool { return $user->can('pharmacy.manage-medicines') && $model->facility_id === currentFacility()?->id; }
    public function delete(User $user, Medicine $model): bool { return $this->update($user, $model); }
    public function viewCost(User $user): bool { return $user->can('pharmacy.view-cost') || $user->can('pharmacy.view-stock-value'); }
}
