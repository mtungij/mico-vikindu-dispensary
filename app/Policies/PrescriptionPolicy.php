<?php

namespace App\Policies;

use App\Models\Prescription;
use App\Models\User;
use App\Policies\Concerns\ChecksClinicalAccess;
use Illuminate\Auth\Access\Response;

class PrescriptionPolicy
{
    use ChecksClinicalAccess;
    public function view(User $user, Prescription $model): bool { return $this->can($user, 'prescriptions.view', $model); }
    public function create(User $user): bool { return $user->can('prescriptions.create'); }
    public function update(User $user, Prescription $model): Response
    {
        if (! $this->sameFacility($user, $model)) {
            return Response::deny('Dawa hii ni ya kituo kingine.');
        }

        return $user->can('prescriptions.update')
            ? Response::allow()
            : Response::deny('Huna ruhusa ya kuhariri dawa hii.');
    }
    public function cancel(User $user, Prescription $model): bool { return $this->can($user, 'prescriptions.cancel', $model); }
}
