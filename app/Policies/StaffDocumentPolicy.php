<?php

namespace App\Policies;

use App\Models\StaffDocument;
use App\Models\StaffProfile;
use App\Models\User;

class StaffDocumentPolicy
{
    public function view(User $user, StaffDocument $document, ?StaffProfile $staffProfile = null): bool
    {
        $profile = $staffProfile ?? $document->staffProfile;

        return $user->can('staff.manage-documents')
            && $profile !== null
            && $document->staff_profile_id === $profile->id
            && $profile->facility_id === currentFacility()?->id;
    }

    public function download(User $user, StaffDocument $document, ?StaffProfile $staffProfile = null): bool
    {
        return $this->view($user, $document, $staffProfile);
    }
}
