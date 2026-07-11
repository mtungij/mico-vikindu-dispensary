<?php

namespace App\Http\Controllers\Dental;

use App\Http\Controllers\Controller;
use App\Models\DentalTreatmentPlan;
use App\Services\DentalReportService;
use Illuminate\Support\Facades\Gate;

class DentalTreatmentPlanPrintController extends Controller
{
    public function __invoke(DentalTreatmentPlan $dentalTreatmentPlan, DentalReportService $reports)
    {
        Gate::authorize('print', $dentalTreatmentPlan);
        abort_unless($dentalTreatmentPlan->facility_id === currentFacility()?->id, 404);
        return view('dental.treatment-plan-print', $reports->planData($dentalTreatmentPlan));
    }
}
