<?php

namespace App\Policies;

use App\Models\StockAdjustment;
use App\Models\User;

class StockAdjustmentPolicy
{
    public function viewAny(User $user): bool { return $user->can('pharmacy.adjust-stock'); }
    public function view(User $user, StockAdjustment $model): bool { return $user->can('pharmacy.adjust-stock') && $model->facility_id === currentFacility()?->id; }
    public function create(User $user): bool { return $user->can('pharmacy.adjust-stock'); }
}
