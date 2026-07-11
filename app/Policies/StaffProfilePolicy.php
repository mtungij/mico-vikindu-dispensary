<?php

namespace App\Policies;

use App\Models\StaffProfile;
use App\Models\User;

class StaffProfilePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('staff.view');
    }

    public function view(User $user, StaffProfile $staffProfile): bool
    {
        return $user->can('staff.view') && $this->sameFacility($staffProfile);
    }

    public function create(User $user): bool
    {
        return $user->can('staff.create');
    }

    public function update(User $user, StaffProfile $staffProfile): bool
    {
        return $user->can('staff.update') && $this->canTouch($user, $staffProfile);
    }

    public function delete(User $user, StaffProfile $staffProfile): bool
    {
        return $user->can('staff.delete')
            && $this->canTouch($user, $staffProfile)
            && $user->id !== $staffProfile->user_id;
    }

    public function restore(User $user, StaffProfile $staffProfile): bool
    {
        return $user->can('staff.restore') && $this->sameFacility($staffProfile);
    }

    public function activate(User $user, StaffProfile $staffProfile): bool
    {
        return $user->can('staff.activate') && $this->canTouch($user, $staffProfile);
    }

    public function suspend(User $user, StaffProfile $staffProfile): bool
    {
        return $user->can('staff.suspend')
            && $this->canTouch($user, $staffProfile)
            && $user->id !== $staffProfile->user_id;
    }

    public function assignRole(User $user, StaffProfile $staffProfile): bool
    {
        return $user->can('staff.assign-role') && $this->canTouch($user, $staffProfile);
    }

    public function assignDepartment(User $user, StaffProfile $staffProfile): bool
    {
        return $user->can('staff.assign-department') && $this->canTouch($user, $staffProfile);
    }

    public function assignDirectPermission(User $user, StaffProfile $staffProfile): bool
    {
        return $user->can('staff.assign-direct-permission') && $this->canTouch($user, $staffProfile);
    }

    public function resetPassword(User $user, StaffProfile $staffProfile): bool
    {
        return $user->can('staff.reset-password') && $this->canTouch($user, $staffProfile);
    }

    public function manageEmployment(User $user, StaffProfile $staffProfile): bool
    {
        return $user->can('staff.manage-employment') && $this->canTouch($user, $staffProfile);
    }

    public function manageEducation(User $user, StaffProfile $staffProfile): bool
    {
        return $user->can('staff.manage-education') && $this->canTouch($user, $staffProfile);
    }

    public function verifyEducation(User $user, StaffProfile $staffProfile): bool
    {
        return $user->can('staff.verify-education') && $this->canTouch($user, $staffProfile);
    }

    public function manageLicense(User $user, StaffProfile $staffProfile): bool
    {
        return $user->can('staff.manage-license') && $this->canTouch($user, $staffProfile);
    }

    public function verifyLicense(User $user, StaffProfile $staffProfile): bool
    {
        return $user->can('staff.verify-license') && $this->canTouch($user, $staffProfile);
    }

    public function manageDocuments(User $user, StaffProfile $staffProfile): bool
    {
        return $user->can('staff.manage-documents') && $this->canTouch($user, $staffProfile);
    }

    public function manageSignature(User $user, StaffProfile $staffProfile): bool
    {
        return $user->can('staff.manage-signature') && $this->canTouch($user, $staffProfile);
    }

    public function verifyDocuments(User $user, StaffProfile $staffProfile): bool
    {
        return $user->can('staff.verify-documents') && $this->canTouch($user, $staffProfile);
    }

    public function manageEmergencyContacts(User $user, StaffProfile $staffProfile): bool
    {
        return $user->can('staff.manage-emergency-contacts') && $this->canTouch($user, $staffProfile);
    }

    public function viewLoginHistory(User $user, StaffProfile $staffProfile): bool
    {
        return $user->can('staff.view-login-history') && $this->sameFacility($staffProfile);
    }

    public function viewActivity(User $user, StaffProfile $staffProfile): bool
    {
        return $user->can('staff.view-activity') && $this->sameFacility($staffProfile);
    }

    public function export(User $user): bool
    {
        return $user->can('staff.export');
    }

    private function canTouch(User $user, StaffProfile $staffProfile): bool
    {
        if (! $this->sameFacility($staffProfile)) {
            return false;
        }

        return $user->is_super_admin || ! $staffProfile->user?->is_super_admin;
    }

    private function sameFacility(StaffProfile $staffProfile): bool
    {
        return $staffProfile->facility_id === currentFacility()?->id;
    }
}
