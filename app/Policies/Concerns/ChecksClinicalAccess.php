<?php

namespace App\Policies\Concerns;

use App\Models\User;

trait ChecksClinicalAccess
{
    protected function sameFacility(User $user, mixed $model): bool
    {
        return $user->is_super_admin || $user->staffProfile?->facility_id === ($model->facility_id ?? null);
    }

    protected function can(User $user, string $permission, mixed $model): bool
    {
        return $this->sameFacility($user, $model) && $user->can($permission);
    }
}
