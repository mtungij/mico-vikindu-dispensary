<?php

namespace App\Policies;

use App\Models\MedicineBatch;
use App\Models\User;

class MedicineBatchPolicy
{
    public function viewAny(User $user): bool { return $user->can('pharmacy.view-stock'); }
    public function view(User $user, MedicineBatch $model): bool { return $user->can('pharmacy.view-stock') && $model->facility_id === currentFacility()?->id; }
    public function quarantine(User $user, MedicineBatch $model): bool { return $user->can('pharmacy.quarantine-batch') && $model->facility_id === currentFacility()?->id; }
    public function recall(User $user, MedicineBatch $model): bool { return $user->can('pharmacy.recall-batch') && $model->facility_id === currentFacility()?->id; }
}
