<?php

namespace App\Policies;

use App\Models\Service;
use App\Models\User;

class ServicePolicy
{
    public function viewAny(User $user): bool { return $user->can('services.view'); }
    public function view(User $user, Service $service): bool { return $user->can('services.view') && $service->facility_id === currentFacility()?->id; }
    public function create(User $user): bool { return $user->can('services.create'); }
    public function update(User $user, Service $service): bool { return $user->can('services.update') && $service->facility_id === currentFacility()?->id; }
    public function delete(User $user, Service $service): bool { return $user->can('services.delete') && $service->facility_id === currentFacility()?->id; }
    public function activate(User $user, Service $service): bool { return $user->can('services.activate') && $service->facility_id === currentFacility()?->id; }
    public function managePrices(User $user, Service $service): bool { return $user->can('services.manage-prices') && $service->facility_id === currentFacility()?->id; }
}
