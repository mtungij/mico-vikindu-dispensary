<?php

namespace App\Policies;

use App\Models\RchChild;
use App\Models\User;

class RchChildPolicy
{
    public function view(User $user, RchChild $model): bool { return $model->facility_id === currentFacility()?->id && $user->can('rch.children.view'); }
    public function create(User $user): bool { return $user->can('rch.children.register'); }
    public function update(User $user, RchChild $model): bool { return $model->facility_id === currentFacility()?->id && $user->can('rch.children.update'); }
}
