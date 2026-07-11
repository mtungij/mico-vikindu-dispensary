<?php

namespace App\Policies;

use App\Models\ClinicalProcedureOrder;
use App\Models\User;
use App\Policies\Concerns\ChecksClinicalAccess;

class ClinicalProcedureOrderPolicy
{
    use ChecksClinicalAccess;
    public function view(User $user, ClinicalProcedureOrder $model): bool { return $this->can($user, 'procedure-orders.view', $model); }
    public function create(User $user): bool { return $user->can('procedure-orders.create'); }
    public function cancel(User $user, ClinicalProcedureOrder $model): bool { return $this->can($user, 'procedure-orders.cancel', $model); }
}
