<?php

namespace App\Policies;

use App\Enums\TriageStatus;
use App\Models\TriageAssessment;
use App\Models\User;
use App\Policies\Concerns\ChecksClinicalAccess;

class TriageAssessmentPolicy
{
    use ChecksClinicalAccess;
    public function view(User $user, TriageAssessment $model): bool { return $this->can($user, 'triage.view', $model); }
    public function create(User $user): bool { return $user->can('triage.record-vitals'); }
    public function update(User $user, TriageAssessment $model): bool { return $this->can($user, $model->status === TriageStatus::Completed ? 'triage.amend' : 'triage.record-vitals', $model); }
    public function complete(User $user, TriageAssessment $model): bool { return $this->can($user, 'triage.complete', $model); }
}
