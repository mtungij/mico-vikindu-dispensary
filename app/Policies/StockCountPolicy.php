<?php

namespace App\Policies;

use App\Models\StockCount;
use App\Models\User;

class StockCountPolicy
{
    public function viewAny(User $user): bool { return $user->can('pharmacy.stock-count'); }
    public function view(User $user, StockCount $model): bool { return $user->can('pharmacy.stock-count') && $model->facility_id === currentFacility()?->id; }
    public function create(User $user): bool { return $user->can('pharmacy.stock-count'); }
}
