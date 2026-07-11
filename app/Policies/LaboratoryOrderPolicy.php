<?php

namespace App\Policies;

use App\Models\LaboratoryOrder;
use App\Models\User;
use App\Policies\Concerns\ChecksClinicalAccess;

class LaboratoryOrderPolicy
{
    use ChecksClinicalAccess;
    public function view(User $user, LaboratoryOrder $model): bool { return $this->can($user, 'laboratory-orders.view', $model); }
    public function create(User $user): bool { return $user->can('laboratory-orders.create'); }
    public function cancel(User $user, LaboratoryOrder $model): bool { return $this->can($user, 'laboratory-orders.cancel', $model); }
}
