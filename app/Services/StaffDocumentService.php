<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\StaffDocument;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class StaffDocumentService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function store(StaffProfile $staffProfile, UploadedFile $file, array $data, User $actor): StaffDocument
    {
        $path = $file->storeAs(
            "staff/{$staffProfile->facility_id}/{$staffProfile->id}/documents",
            str()->uuid()->toString().'.'.strtolower($file->getClientOriginalExtension()),
            'local',
        );

        $document = StaffDocument::query()->create([
            ...$data,
            'staff_profile_id' => $staffProfile->id,
            'file_path' => $path,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'uploaded_by' => $actor->id,
        ]);

        ActivityLog::query()->create([
            'user_id' => $actor->id,
            'event' => 'document_uploaded',
            'subject_type' => StaffDocument::class,
            'subject_id' => $document->id,
            'old_values' => [],
            'new_values' => $document->only(['document_type', 'document_name', 'file_size']),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return $document;
    }

    public function stream(StaffDocument $document, bool $download = false)
    {
        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->response(
            $document->file_path,
            str($document->document_name)->slug()->append('.'.pathinfo($document->file_path, PATHINFO_EXTENSION))->toString(),
            ['Content-Type' => $document->mime_type ?: 'application/octet-stream'],
            $download ? 'attachment' : 'inline',
        );
    }
}
