<?php

namespace App\Policies;

use App\Models\Dispensing;
use App\Models\Prescription;
use App\Models\User;

class PrescriptionDispensingPolicy
{
    public function viewQueue(User $user): bool { return $user->can('pharmacy.view-queue'); }
    public function view(User $user, Prescription $model): bool { return $user->can('pharmacy.view-prescription') && $model->facility_id === currentFacility()?->id; }
    public function dispense(User $user, Prescription $model): bool { return $user->can('pharmacy.dispense') && $model->facility_id === currentFacility()?->id; }
    public function reverse(User $user, Dispensing $model): bool { return $user->can('pharmacy.reverse-dispensing') && $model->facility_id === currentFacility()?->id; }
}
