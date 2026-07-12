<?php

namespace App\Policies;

use App\Models\User;

class InsuranceClaimPolicy
{
    public function viewAny(User $user): bool { return $user->can('insurance.claims.view'); }
    public function view(User $user, mixed $claim): bool { return $claim->facility_id === currentFacility()?->id && $user->can('insurance.claims.view'); }
    public function create(User $user): bool { return $user->can('insurance.claims.prepare'); }
    public function update(User $user, mixed $claim): bool { return $claim->facility_id === currentFacility()?->id && ! $claim->isImmutable() && $user->can('insurance.claims.update-draft'); }
    public function validate(User $user, mixed $claim): bool { return $claim->facility_id === currentFacility()?->id && $user->can('insurance.claims.validate'); }
    public function submit(User $user, mixed $claim): bool { return $claim->facility_id === currentFacility()?->id && $user->can('insurance.claims.submit'); }
    public function correct(User $user, mixed $claim): bool { return $claim->facility_id === currentFacility()?->id && in_array($claim->status, ['rejected','correction_required'], true) && $user->can('insurance.claims.correct'); }
    public function print(User $user, mixed $claim): bool { return $claim->facility_id === currentFacility()?->id && $user->can('insurance.claims.print'); }
}
