<?php

namespace App\Http\Controllers\Laboratory;

use App\Http\Controllers\Controller;
use App\Models\LaboratoryResult;
use App\Services\LaboratoryReportService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class LaboratoryResultPrintController extends Controller
{
    public function __invoke(LaboratoryResult $laboratoryResult, LaboratoryReportService $reports): View
    {
        Gate::authorize('print', $laboratoryResult);
        abort_unless($laboratoryResult->facility_id === currentFacility()?->id, 404);

        return view('laboratory.result-print', [
            'result' => $laboratoryResult->load([
                'order.patient',
                'order.visit',
                'orderItem.service',
                'sample.specimenType',
                'test',
                'values',
                'verifier.staffProfile.activeSignature',
            ]),
            'signaturePath' => $reports->verifierSignaturePath($laboratoryResult),
            'facility' => currentFacility(),
        ]);
    }
}
