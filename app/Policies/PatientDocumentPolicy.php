<?php

namespace App\Policies;

use App\Models\Patient;
use App\Models\PatientDocument;
use App\Models\User;

class PatientDocumentPolicy
{
    public function view(User $user, PatientDocument $document, ?Patient $patient = null): bool
    {
        $patient ??= $document->patient;
        return $user->can('patients.manage-documents') && $document->patient_id === $patient->id && $patient->facility_id === currentFacility()?->id;
    }

    public function download(User $user, PatientDocument $document, ?Patient $patient = null): bool { return $this->view($user, $document, $patient); }
}
