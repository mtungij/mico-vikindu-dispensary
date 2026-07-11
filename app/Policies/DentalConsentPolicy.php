<?php

namespace App\Policies;

use App\Models\DentalConsent;
use App\Models\User;

class DentalConsentPolicy { public function view(User $u, DentalConsent $m): bool { return $m->facility_id === currentFacility()?->id && $u->can('dental.manage-consents'); } public function create(User $u): bool { return $u->can('dental.manage-consents'); } public function update(User $u, DentalConsent $m): bool { return $m->facility_id === currentFacility()?->id && $u->can('dental.manage-consents') && $m->signed_at === null; } }
