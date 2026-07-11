<?php

namespace App\Policies;

use App\Models\PharmacyReturn;
use App\Models\User;

class PharmacyReturnPolicy
{
    public function viewAny(User $user): bool { return $user->can('pharmacy.manage-patient-returns'); }
    public function view(User $user, PharmacyReturn $model): bool { return $user->can('pharmacy.manage-patient-returns') && $model->facility_id === currentFacility()?->id; }
    public function create(User $user): bool { return $user->can('pharmacy.manage-patient-returns'); }
}
