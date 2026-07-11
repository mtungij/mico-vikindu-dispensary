<?php

namespace App\Policies;

use App\Models\PurchaseOrder;
use App\Models\User;

class PurchaseOrderPolicy
{
    public function viewAny(User $user): bool { return $user->can('pharmacy.receive-stock'); }
    public function view(User $user, PurchaseOrder $model): bool { return $user->can('pharmacy.receive-stock') && $model->facility_id === currentFacility()?->id; }
    public function create(User $user): bool { return $user->can('pharmacy.receive-stock'); }
}
