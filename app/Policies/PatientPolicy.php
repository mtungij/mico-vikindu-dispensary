<?php

namespace App\Policies;

use App\Models\Patient;
use App\Models\User;

class PatientPolicy
{
    public function viewAny(User $user): bool { return $user->can('patients.view'); }
    public function view(User $user, Patient $patient): bool { return $user->can('patients.view') && $patient->facility_id === currentFacility()?->id; }
    public function create(User $user): bool { return $user->can('patients.create'); }
    public function quickRegister(User $user): bool { return $user->can('patients.quick-register'); }
    public function update(User $user, Patient $patient): bool { return $user->can('patients.update') && $patient->facility_id === currentFacility()?->id; }
    public function archive(User $user, Patient $patient): bool { return $user->can('patients.archive') && $patient->facility_id === currentFacility()?->id; }
    public function restore(User $user, Patient $patient): bool { return $user->can('patients.restore') && $patient->facility_id === currentFacility()?->id; }
    public function managePayers(User $user, Patient $patient): bool { return $user->can('patients.manage-payers') && $patient->facility_id === currentFacility()?->id; }
    public function manageDocuments(User $user, Patient $patient): bool { return $user->can('patients.manage-documents') && $patient->facility_id === currentFacility()?->id; }
    public function printCard(User $user, Patient $patient): bool { return $user->can('patients.print-card') && $patient->facility_id === currentFacility()?->id; }
    public function replaceCard(User $user, Patient $patient): bool { return $user->can('patients.replace-card') && $patient->facility_id === currentFacility()?->id; }
    public function export(User $user): bool { return $user->can('patients.export'); }
}
