<?php

namespace App\Policies;

use App\Models\JobTitle;
use App\Models\User;

class JobTitlePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('job-titles.view');
    }

    public function view(User $user, JobTitle $jobTitle): bool
    {
        return $user->can('job-titles.view') && $this->belongsToCurrentFacility($jobTitle);
    }

    public function create(User $user): bool
    {
        return $user->can('job-titles.create');
    }

    public function update(User $user, JobTitle $jobTitle): bool
    {
        return $user->can('job-titles.update') && $this->belongsToCurrentFacility($jobTitle);
    }

    public function delete(User $user, JobTitle $jobTitle): bool
    {
        return $user->can('job-titles.delete') && $this->belongsToCurrentFacility($jobTitle);
    }

    public function activate(User $user, JobTitle $jobTitle): bool
    {
        return $user->can('job-titles.activate') && $this->belongsToCurrentFacility($jobTitle);
    }

    private function belongsToCurrentFacility(JobTitle $jobTitle): bool
    {
        return $jobTitle->facility_id === currentFacility()?->id;
    }
}
