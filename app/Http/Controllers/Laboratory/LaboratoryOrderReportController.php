<?php

namespace App\Http\Controllers\Laboratory;

use App\Http\Controllers\Controller;
use App\Models\LaboratoryOrder;
use App\Services\LaboratoryReportService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class LaboratoryOrderReportController extends Controller
{
    public function __invoke(LaboratoryOrder $laboratoryOrder, LaboratoryReportService $reports): View
    {
        Gate::authorize('laboratory-results.print');
        abort_unless($laboratoryOrder->facility_id === currentFacility()?->id, 404);

        return view('laboratory.order-report', [
            'order' => $laboratoryOrder->load([
                'patient',
                'visit',
                'items.service',
                'results.values',
                'results.test',
                'results.sample.specimenType',
                'results.verifier.staffProfile.activeSignature',
            ]),
            'results' => $reports->orderReleasedResults($laboratoryOrder),
            'facility' => currentFacility(),
        ]);
    }
}
