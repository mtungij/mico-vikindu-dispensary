<?php

namespace App\Policies;

use App\Models\User;

class InsuranceClaimBatchPolicy
{
    public function viewAny(User $user): bool { return $user->can('insurance.claim-batches.view'); }
    public function view(User $user, mixed $batch): bool { return $batch->facility_id === currentFacility()?->id && $user->can('insurance.claim-batches.view'); }
    public function create(User $user): bool { return $user->can('insurance.claim-batches.create'); }
    public function submit(User $user, mixed $batch): bool { return $batch->facility_id === currentFacility()?->id && $batch->status === 'ready' && $user->can('insurance.claim-batches.submit'); }
}
