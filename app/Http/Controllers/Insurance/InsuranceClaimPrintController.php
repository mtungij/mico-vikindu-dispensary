<?php

namespace App\Http\Controllers\Insurance;

use App\Models\InsuranceClaim;
use Illuminate\Support\Facades\Gate;

class InsuranceClaimPrintController
{
    public function __invoke(InsuranceClaim $insuranceClaim)
    {
        Gate::authorize('print', $insuranceClaim);

        return view('insurance.print.claim', ['claim' => $insuranceClaim->load(['facility','patient','provider','scheme','membership','items'])]);
    }
}
