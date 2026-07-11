<?php

namespace App\Http\Controllers;

use App\Models\FacilityDocument;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FacilityDocumentController extends Controller
{
    public function view(FacilityDocument $document): Response|StreamedResponse
    {
        Gate::authorize('view', $document);

        return Storage::disk('local')->response($document->file_path);
    }

    public function download(FacilityDocument $document): StreamedResponse
    {
        Gate::authorize('view', $document);

        return Storage::disk('local')->download($document->file_path);
    }
}
