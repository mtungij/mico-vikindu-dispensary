<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\PatientDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class PatientDocumentService
{
    public function store(Patient $patient, UploadedFile $file, array $data, $actor): PatientDocument
    {
        $path = $file->storeAs("patients/{$patient->facility_id}/{$patient->id}/documents", str()->uuid()->toString().'.'.strtolower($file->getClientOriginalExtension()), 'local');

        return $patient->documents()->create([
            ...$data,
            'file_path' => $path,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'uploaded_by' => $actor->id,
        ]);
    }

    public function stream(PatientDocument $document, bool $download = false)
    {
        abort_unless(Storage::disk('local')->exists($document->file_path), 404);
        return Storage::disk('local')->response($document->file_path, str($document->document_name)->slug()->append('.'.pathinfo($document->file_path, PATHINFO_EXTENSION))->toString(), ['Content-Type' => $document->mime_type ?: 'application/octet-stream'], $download ? 'attachment' : 'inline');
    }
}
