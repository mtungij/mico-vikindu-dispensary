<?php
namespace App\Policies;
use App\Models\Receipt; use App\Models\User;
class ReceiptPolicy { public function view(User $user, Receipt $receipt): bool { return $receipt->facility_id === currentFacility()?->id && $user->can('billing.view-payment-history'); } public function print(User $user, Receipt $receipt): bool { return $receipt->facility_id === currentFacility()?->id && ($user->can('billing.reprint-receipt') || $user->can('billing.receive-payment')); } }
