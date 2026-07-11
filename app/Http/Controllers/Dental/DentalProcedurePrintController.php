<?php

namespace App\Http\Controllers\Dental;

use App\Http\Controllers\Controller;
use App\Models\DentalProcedure;
use App\Services\DentalReportService;
use Illuminate\Support\Facades\Gate;

class DentalProcedurePrintController extends Controller
{
    public function __invoke(DentalProcedure $dentalProcedure, DentalReportService $reports)
    {
        Gate::authorize('print', $dentalProcedure);
        abort_unless($dentalProcedure->facility_id === currentFacility()?->id, 404);
        return view('dental.procedure-print', $reports->procedureData($dentalProcedure));
    }
}
