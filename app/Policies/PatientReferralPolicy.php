<?php

namespace App\Policies;

use App\Models\PatientReferral;
use App\Models\User;
use App\Policies\Concerns\ChecksClinicalAccess;

class PatientReferralPolicy
{
    use ChecksClinicalAccess;
    public function view(User $user, PatientReferral $model): bool { return $this->can($user, 'referrals.view', $model); }
    public function create(User $user): bool { return $user->can('referrals.create'); }
    public function update(User $user, PatientReferral $model): bool { return $this->can($user, 'referrals.update', $model); }
    public function cancel(User $user, PatientReferral $model): bool { return $this->can($user, 'referrals.cancel', $model); }
    public function print(User $user, PatientReferral $model): bool { return $this->can($user, 'referrals.print', $model); }
}
