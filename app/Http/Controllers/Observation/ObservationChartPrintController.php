<?php

namespace App\Http\Controllers\Observation;

use App\Http\Controllers\Controller;
use App\Models\ObservationAdmission;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class ObservationChartPrintController extends Controller { public function __invoke(ObservationAdmission $observationAdmission): View { Gate::authorize('observation.print-chart'); abort_unless($observationAdmission->facility_id === currentFacility()?->id, 404); return view('observation.chart-print', ['admission'=>$observationAdmission->load(['patient','bed','room','observations','orders','medicationAdministrations','ivFluids','discharge'])]); } }
