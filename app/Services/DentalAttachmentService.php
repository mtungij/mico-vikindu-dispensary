<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\DentalAttachment;
use App\Models\DentalEncounter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class DentalAttachmentService
{
    public function store(DentalEncounter $encounter, UploadedFile $file, array $data, $actor): DentalAttachment
    {
        abort_unless($encounter->facility_id === currentFacility()?->id, 404);
        $path = $file->store("dental/{$encounter->facility_id}/{$encounter->id}", 'local');
        $attachment = DentalAttachment::query()->create(['facility_id'=>$encounter->facility_id,'patient_id'=>$encounter->patient_id,'dental_encounter_id'=>$encounter->id,'tooth_number'=>$data['tooth_number'] ?? null,'attachment_type'=>$data['attachment_type'],'title'=>$data['title'],'description'=>$data['description'] ?? null,'file_path'=>$path,'mime_type'=>$file->getMimeType() ?: 'application/octet-stream','file_size'=>$file->getSize(),'captured_at'=>$data['captured_at'] ?? null,'uploaded_by'=>$actor->id]);
        ActivityLog::query()->create(['user_id'=>$actor->id,'event'=>'dental_attachment_uploaded','subject_type'=>DentalAttachment::class,'subject_id'=>$attachment->id,'new_values'=>['attachment_type'=>$attachment->attachment_type]]);
        return $attachment;
    }
    public function response(DentalAttachment $attachment, bool $download = false)
    {
        abort_unless($attachment->facility_id === currentFacility()?->id, 404);
        return $download ? Storage::disk('local')->download($attachment->file_path, basename($attachment->file_path)) : Storage::disk('local')->response($attachment->file_path);
    }
}
