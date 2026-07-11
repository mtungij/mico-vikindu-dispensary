<?php

namespace App\Policies;

use App\Models\ClinicalAlert;
use App\Models\User;
use App\Policies\Concerns\ChecksClinicalAccess;

class ClinicalAlertPolicy
{
    use ChecksClinicalAccess;
    public function view(User $user, ClinicalAlert $model): bool { return $this->can($user, 'clinical-alerts.view', $model); }
    public function acknowledge(User $user, ClinicalAlert $model): bool { return $this->can($user, 'clinical-alerts.acknowledge', $model); }
    public function resolve(User $user, ClinicalAlert $model): bool { return $this->can($user, 'clinical-alerts.resolve', $model); }
    public function dismiss(User $user, ClinicalAlert $model): bool { return $this->can($user, 'clinical-alerts.dismiss', $model); }
}
