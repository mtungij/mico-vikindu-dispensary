<?php

namespace App\Http\Controllers\Patients;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use Illuminate\Support\Facades\Gate;

class PatientCardController extends Controller
{
    public function __invoke(Patient $patient)
    {
        Gate::authorize('printCard', $patient);
        return view('patients.card', ['patient' => $patient->load('primaryPayerProfile'), 'facility' => currentFacility()]);
    }
}
