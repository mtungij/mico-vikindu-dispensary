<?php

namespace App\Policies;

use App\Models\ServiceCategory;
use App\Models\User;

class ServiceCategoryPolicy
{
    public function viewAny(User $user): bool { return $user->can('service-categories.view'); }
    public function view(User $user, ServiceCategory $category): bool { return $user->can('service-categories.view') && $category->facility_id === currentFacility()?->id; }
    public function create(User $user): bool { return $user->can('service-categories.create'); }
    public function update(User $user, ServiceCategory $category): bool { return $user->can('service-categories.update') && $category->facility_id === currentFacility()?->id; }
    public function delete(User $user, ServiceCategory $category): bool { return $user->can('service-categories.delete') && $category->facility_id === currentFacility()?->id; }
    public function activate(User $user, ServiceCategory $category): bool { return $user->can('service-categories.activate') && $category->facility_id === currentFacility()?->id; }
}
