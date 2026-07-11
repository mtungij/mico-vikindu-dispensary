<?php

namespace App\Policies;

use App\Models\DentalAttachment;
use App\Models\User;

class DentalAttachmentPolicy { public function view(User $u, DentalAttachment $m): bool { return $m->facility_id === currentFacility()?->id && $u->can('dental.manage-attachments'); } public function create(User $u): bool { return $u->can('dental.manage-attachments'); } }
