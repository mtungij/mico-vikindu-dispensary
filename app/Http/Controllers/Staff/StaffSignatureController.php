<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\StaffProfile;
use App\Models\StaffSignature;
use App\Services\StaffSignatureService;
use Illuminate\Support\Facades\Gate;

class StaffSignatureController extends Controller
{
    public function view(StaffProfile $staffProfile, StaffSignature $signature, StaffSignatureService $service)
    {
        abort_unless($signature->staff_id === $staffProfile->id, 404);
        Gate::authorize('view', $signature);

        return $service->stream($signature);
    }
}
