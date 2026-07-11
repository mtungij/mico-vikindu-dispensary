<?php

namespace App\Http\Controllers\Patients;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\PatientDocument;
use App\Services\PatientDocumentService;
use Illuminate\Support\Facades\Gate;

class PatientDocumentController extends Controller
{
    public function view(Patient $patient, PatientDocument $document, PatientDocumentService $service)
    {
        abort_unless($document->patient_id === $patient->id, 404);
        Gate::authorize('view', [$document, $patient]);
        return $service->stream($document);
    }

    public function download(Patient $patient, PatientDocument $document, PatientDocumentService $service)
    {
        abort_unless($document->patient_id === $patient->id, 404);
        Gate::authorize('download', [$document, $patient]);
        return $service->stream($document, true);
    }
}
