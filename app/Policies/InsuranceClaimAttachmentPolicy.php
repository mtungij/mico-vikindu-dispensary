<?php

namespace App\Policies;

use App\Models\User;

class InsuranceClaimAttachmentPolicy
{
    public function view(User $user, mixed $attachment): bool { return $attachment->facility_id === currentFacility()?->id && $user->can('insurance.attachments.view'); }
    public function create(User $user): bool { return $user->can('insurance.attachments.manage'); }
    public function update(User $user, mixed $attachment): bool { return $attachment->facility_id === currentFacility()?->id && $user->can('insurance.attachments.manage'); }
}
