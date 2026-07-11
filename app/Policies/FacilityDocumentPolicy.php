<?php

namespace App\Policies;

use App\Models\FacilityDocument;
use App\Models\User;

class FacilityDocumentPolicy
{
    public function uploadFacilityDocument(User $user): bool
    {
        return $user->is_super_admin;
    }

    public function view(User $user, FacilityDocument $facilityDocument): bool
    {
        return $user->is_super_admin;
    }

    public function deleteFacilityDocument(User $user, FacilityDocument $facilityDocument): bool
    {
        return $user->is_super_admin;
    }
}
