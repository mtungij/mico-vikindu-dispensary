<?php

namespace App\Policies;

use App\Models\StockTransfer;
use App\Models\User;

class StockTransferPolicy
{
    public function viewAny(User $user): bool { return $user->can('pharmacy.transfer-stock'); }
    public function view(User $user, StockTransfer $model): bool { return $user->can('pharmacy.transfer-stock') && $model->facility_id === currentFacility()?->id; }
    public function create(User $user): bool { return $user->can('pharmacy.transfer-stock'); }
}
