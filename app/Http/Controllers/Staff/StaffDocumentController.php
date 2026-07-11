<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\StaffDocument;
use App\Models\StaffProfile;
use App\Services\StaffDocumentService;
use Illuminate\Support\Facades\Gate;

class StaffDocumentController extends Controller
{
    public function view(StaffProfile $staffProfile, StaffDocument $document, StaffDocumentService $service)
    {
        abort_unless($document->staff_profile_id === $staffProfile->id, 404);
        Gate::authorize('view', [$document, $staffProfile]);

        return $service->stream($document);
    }

    public function download(StaffProfile $staffProfile, StaffDocument $document, StaffDocumentService $service)
    {
        abort_unless($document->staff_profile_id === $staffProfile->id, 404);
        Gate::authorize('download', [$document, $staffProfile]);

        return $service->stream($document, true);
    }
}
