<?php

namespace App\Policies;

use App\Models\StockLocation;
use App\Models\User;

class StockLocationPolicy
{
    public function viewAny(User $user): bool { return $user->can('pharmacy.manage-stock-locations') || $user->can('pharmacy.view-stock'); }
    public function create(User $user): bool { return $user->can('pharmacy.manage-stock-locations'); }
    public function update(User $user, StockLocation $model): bool { return $user->can('pharmacy.manage-stock-locations') && $model->facility_id === currentFacility()?->id; }
    public function delete(User $user, StockLocation $model): bool { return $this->update($user, $model); }
}
