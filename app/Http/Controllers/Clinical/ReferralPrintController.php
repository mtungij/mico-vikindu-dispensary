<?php

namespace App\Http\Controllers\Clinical;

use App\Http\Controllers\Controller;
use App\Models\PatientReferral;
use Illuminate\Support\Facades\Gate;

class ReferralPrintController extends Controller
{
    public function __invoke(PatientReferral $referral)
    {
        abort_unless($referral->facility_id === currentFacility()?->id, 404);
        Gate::authorize('print', $referral);
        $referral->load(['patient', 'visit', 'encounter']);
        return view('referrals.print', ['referral' => $referral, 'facility' => currentFacility()]);
    }
}
