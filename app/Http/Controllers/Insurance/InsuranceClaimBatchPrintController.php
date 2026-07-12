<?php

namespace App\Http\Controllers\Insurance;

use App\Models\InsuranceClaimBatch;
use Illuminate\Support\Facades\Gate;

class InsuranceClaimBatchPrintController
{
    public function __invoke(InsuranceClaimBatch $claimBatch)
    {
        Gate::authorize('view', $claimBatch);

        return view('insurance.print.batch', ['batch' => $claimBatch->load('claims.patient')]);
    }
}
