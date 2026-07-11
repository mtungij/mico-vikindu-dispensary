<?php

namespace App\Http\Controllers\Dental;

use App\Http\Controllers\Controller;
use App\Models\DentalEncounter;
use App\Services\DentalReportService;
use Illuminate\Support\Facades\Gate;

class DentalChartPrintController extends Controller
{
    public function __invoke(DentalEncounter $dentalEncounter, DentalReportService $reports)
    {
        Gate::authorize('print', $dentalEncounter);
        abort_unless($dentalEncounter->facility_id === currentFacility()?->id, 404);
        return view('dental.chart-print', $reports->chartData($dentalEncounter));
    }
}
