<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\StaffProfile;
use App\Models\StaffSignature;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StaffSignatureService
{
    public function store(StaffProfile $staffProfile, UploadedFile $file, $actor): StaffSignature
    {
        return DB::transaction(function () use ($staffProfile, $file, $actor): StaffSignature {
            $hadActive = $staffProfile->signatures()->where('is_active', true)->exists();
            $staffProfile->signatures()->where('is_active', true)->update(['is_active' => false]);

            $path = $file->storeAs(
                "staff/{$staffProfile->facility_id}/{$staffProfile->id}/signatures",
                str()->uuid()->toString().'.'.strtolower($file->getClientOriginalExtension()),
                'local',
            );

            $signature = StaffSignature::query()->create([
                'facility_id' => $staffProfile->facility_id,
                'staff_id' => $staffProfile->id,
                'signature_path' => $path,
                'uploaded_by' => $actor->id,
                'uploaded_at' => now(),
                'is_active' => true,
            ]);

            ActivityLog::query()->create([
                'user_id' => $actor->id,
                'event' => $hadActive ? 'signature_replaced' : 'signature_uploaded',
                'subject_type' => StaffProfile::class,
                'subject_id' => $staffProfile->id,
                'new_values' => ['signature_id' => $signature->id],
            ]);

            return $signature;
        });
    }

    public function deleteActive(StaffProfile $staffProfile, $actor): void
    {
        DB::transaction(function () use ($staffProfile, $actor): void {
            $signature = $staffProfile->activeSignature()->lockForUpdate()->first();
            if (! $signature) {
                return;
            }

            $signature->update(['is_active' => false]);
            $signature->delete();

            ActivityLog::query()->create([
                'user_id' => $actor->id,
                'event' => 'signature_deleted',
                'subject_type' => StaffProfile::class,
                'subject_id' => $staffProfile->id,
                'old_values' => ['signature_id' => $signature->id],
            ]);
        });
    }

    public function stream(StaffSignature $signature): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($signature->signature_path), 404);

        return Storage::disk('local')->response($signature->signature_path);
    }
}
