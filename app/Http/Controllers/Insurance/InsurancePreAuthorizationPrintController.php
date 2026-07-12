<?php

namespace App\Http\Controllers\Insurance;

use App\Models\InsurancePreAuthorization;
use Illuminate\Support\Facades\Gate;

class InsurancePreAuthorizationPrintController
{
    public function __invoke(InsurancePreAuthorization $preAuthorization)
    {
        Gate::authorize('insurance.pre-authorizations.view');
        abort_unless($preAuthorization->facility_id === currentFacility()?->id, 403);

        return view('insurance.print.pre-authorization', ['authorization' => $preAuthorization->load(['patient','membership'])]);
    }
}
