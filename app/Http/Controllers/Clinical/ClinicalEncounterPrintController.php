<?php

namespace App\Http\Controllers\Clinical;

use App\Http\Controllers\Controller;
use App\Models\ClinicalEncounter;
use Illuminate\Support\Facades\Gate;

class ClinicalEncounterPrintController extends Controller
{
    public function __invoke(ClinicalEncounter $encounter)
    {
        abort_unless($encounter->facility_id === currentFacility()?->id, 404);
        Gate::authorize('print', $encounter);
        $encounter->load(['patient', 'visit.latestTriageAssessment', 'provider', 'complaints', 'examinations', 'diagnoses', 'laboratoryOrders.items', 'prescriptions.items', 'procedureOrders', 'appointments', 'referrals']);
        return view('clinical.encounter-print', ['encounter' => $encounter, 'facility' => currentFacility()]);
    }
}
