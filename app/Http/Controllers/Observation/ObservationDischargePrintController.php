<?php

namespace App\Http\Controllers\Observation;

use App\Http\Controllers\Controller;
use App\Models\ObservationDischarge;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class ObservationDischargePrintController extends Controller { public function __invoke(ObservationDischarge $observationDischarge): View { Gate::authorize('print', $observationDischarge); abort_unless($observationDischarge->facility_id === currentFacility()?->id, 404); return view('observation.discharge-print', ['discharge'=>$observationDischarge->load(['patient','admission'])]); } }
