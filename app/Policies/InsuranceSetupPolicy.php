<?php

namespace App\Policies;

use App\Models\User;

class InsuranceSetupPolicy
{
    public function viewAny(User $user): bool { return $user->can('insurance.access') || $user->can('insurance.manage-settings'); }
    public function view(User $user, mixed $model): bool { return $this->sameFacility($model) && $this->viewAny($user); }
    public function create(User $user): bool { return $user->can('insurance.manage-settings') || $user->can('insurance.manage-providers'); }
    public function update(User $user, mixed $model): bool { return $this->sameFacility($model) && $this->create($user); }
    public function delete(User $user, mixed $model): bool { return $this->sameFacility($model) && $this->create($user) && ! ($model->is_system ?? false); }
    protected function sameFacility(mixed $model): bool { return ! isset($model->facility_id) || $model->facility_id === null || $model->facility_id === currentFacility()?->id; }
}
