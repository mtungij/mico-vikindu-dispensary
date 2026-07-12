<?php
namespace App\Policies;
use App\Models\User;
class BillingSetupPolicy { public function viewAny(User $user): bool { return $user->can('billing.access'); } public function view(User $user, mixed $model): bool { return ($model->facility_id ?? currentFacility()?->id) === currentFacility()?->id && $user->can('billing.access'); } public function create(User $user): bool { return $user->can('billing.manage-settings'); } public function update(User $user, mixed $model): bool { return $this->view($user,$model) && $user->can('billing.manage-settings'); } }
