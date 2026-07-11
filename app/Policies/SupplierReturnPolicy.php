<?php

namespace App\Policies;

use App\Models\SupplierReturn;
use App\Models\User;

class SupplierReturnPolicy
{
    public function viewAny(User $user): bool { return $user->can('pharmacy.return-to-supplier'); }
    public function view(User $user, SupplierReturn $model): bool { return $user->can('pharmacy.return-to-supplier') && $model->facility_id === currentFacility()?->id; }
    public function create(User $user): bool { return $user->can('pharmacy.return-to-supplier'); }
}
