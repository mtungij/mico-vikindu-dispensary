<?php

namespace App\Policies;

use App\Models\PmtctRecord;
use App\Models\User;

class PmtctRecordPolicy
{
    public function view(User $user, PmtctRecord $model): bool { return $model->facility_id === currentFacility()?->id && $user->can('rch.pmtct.view'); }
    public function manage(User $user, PmtctRecord $model): bool { return $model->facility_id === currentFacility()?->id && $user->can('rch.pmtct.manage'); }
}
