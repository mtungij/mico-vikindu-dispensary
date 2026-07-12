<?php
namespace App\Policies;
use App\Models\PaymentMethod; use App\Models\User;
class PaymentMethodPolicy { public function viewAny(User $user): bool { return $user->can('billing.manage-payment-methods') || $user->can('billing.access'); } public function view(User $user, PaymentMethod $model): bool { return ($model->facility_id === null || $model->facility_id === currentFacility()?->id) && $this->viewAny($user); } public function create(User $user): bool { return $user->can('billing.manage-payment-methods'); } public function update(User $user, PaymentMethod $model): bool { return $this->view($user,$model) && $user->can('billing.manage-payment-methods'); } public function delete(User $user, PaymentMethod $model): bool { return $this->update($user,$model) && ! $model->is_system; } }
