<?php

namespace App\Policies;

use App\Models\Prescription;
use App\Models\User;
use App\Policies\Concerns\ChecksClinicalAccess;

class PrescriptionPolicy
{
    use ChecksClinicalAccess;
    public function view(User $user, Prescription $model): bool { return $this->can($user, 'prescriptions.view', $model); }
    public function create(User $user): bool { return $user->can('prescriptions.create'); }
    public function update(User $user, Prescription $model): bool { return $this->can($user, 'prescriptions.update', $model); }
    public function cancel(User $user, Prescription $model): bool { return $this->can($user, 'prescriptions.cancel', $model); }
}
