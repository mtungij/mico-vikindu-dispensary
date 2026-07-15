<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;
use App\Policies\Concerns\ChecksClinicalAccess;

class AppointmentPolicy
{
    use ChecksClinicalAccess;
    public function view(User $user, Appointment $model): bool { return $this->can($user, 'appointments.view', $model); }
    public function create(User $user): bool { return $user->can('appointments.create'); }
    public function update(User $user, Appointment $model): bool { return $this->can($user, 'appointments.edit', $model) || $this->can($user, 'appointments.update', $model); }
    public function cancel(User $user, Appointment $model): bool { return $this->can($user, 'appointments.cancel', $model); }
    public function reschedule(User $user, Appointment $model): bool { return $this->can($user, 'appointments.reschedule', $model); }
    public function checkin(User $user, Appointment $model): bool { return $this->can($user, 'appointments.check-in', $model) || $this->can($user, 'appointments.checkin', $model); }
}
