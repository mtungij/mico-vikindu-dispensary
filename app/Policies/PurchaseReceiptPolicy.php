<?php

namespace App\Policies;

use App\Models\PurchaseReceipt;
use App\Models\User;

class PurchaseReceiptPolicy
{
    public function viewAny(User $user): bool { return $user->can('pharmacy.receive-stock'); }
    public function view(User $user, PurchaseReceipt $model): bool { return $user->can('pharmacy.receive-stock') && $model->facility_id === currentFacility()?->id; }
    public function create(User $user): bool { return $user->can('pharmacy.receive-stock'); }
    public function verify(User $user, PurchaseReceipt $model): bool { return $user->can('pharmacy.verify-receipt') && $model->facility_id === currentFacility()?->id; }
}
