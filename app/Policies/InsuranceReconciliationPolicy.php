<?php

namespace App\Policies;

use App\Models\User;

class InsuranceReconciliationPolicy
{
    public function manage(User $user): bool { return $user->can('insurance.reconciliation.manage'); }
}
