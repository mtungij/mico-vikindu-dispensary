<?php
namespace App\Policies;
use App\Models\Payment; use App\Models\User;
class PaymentPolicy { public function viewAny(User $user): bool { return $user->can('billing.view-payment-history'); } public function view(User $user, Payment $payment): bool { return $payment->facility_id === currentFacility()?->id && $user->can('billing.view-payment-history'); } public function create(User $user): bool { return $user->can('billing.receive-payment'); } public function reverse(User $user, Payment $payment): bool { return $payment->facility_id === currentFacility()?->id && $payment->status === 'confirmed' && $user->can('billing.process-reversal'); } }
