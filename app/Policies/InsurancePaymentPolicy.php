<?php

namespace App\Policies;

use App\Models\User;

class InsurancePaymentPolicy
{
    public function viewAny(User $user): bool { return $user->can('insurance.payments.view'); }
    public function view(User $user, mixed $payment): bool { return $payment->facility_id === currentFacility()?->id && $user->can('insurance.payments.view'); }
    public function create(User $user): bool { return $user->can('insurance.payments.record'); }
    public function allocate(User $user, mixed $payment): bool { return $payment->facility_id === currentFacility()?->id && $user->can('insurance.payments.allocate'); }
}
