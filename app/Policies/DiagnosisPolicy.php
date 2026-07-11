<?php

namespace App\Policies;

use App\Models\Diagnosis;
use App\Models\User;
use App\Policies\Concerns\ChecksClinicalAccess;

class DiagnosisPolicy
{
    use ChecksClinicalAccess;
    public function view(User $user, Diagnosis $model): bool { return $this->can($user, 'diagnoses.view', $model); }
    public function create(User $user): bool { return $user->can('diagnoses.create'); }
    public function update(User $user, Diagnosis $model): bool { return $this->can($user, 'diagnoses.update', $model); }
    public function markError(User $user, Diagnosis $model): bool { return $this->can($user, 'diagnoses.mark-error', $model); }
}
