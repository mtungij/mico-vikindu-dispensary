<?php

namespace App\Services;

use App\Enums\LaboratoryResultStatus;
use App\Models\ActivityLog;
use App\Models\LaboratoryResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LaboratoryResultVerificationService
{
    public function verify(LaboratoryResult $result, $actor): LaboratoryResult
    {
        return DB::transaction(function () use ($result, $actor) {
            $result = LaboratoryResult::query()->lockForUpdate()->findOrFail($result->id);
            if ($result->result_status !== LaboratoryResultStatus::PendingVerification) {
                throw ValidationException::withMessages(['result' => 'Result haipo kwenye verification queue.']);
            }
            if (config('facility.laboratory_require_independent_verification', false) && $result->entered_by === $actor->id && ! $actor->can('laboratory-results.verify-own')) {
                throw ValidationException::withMessages(['verifier' => 'Verifier hawezi kuwa aliye-enter result.']);
            }
            if (config('facility.laboratory_signature_required_for_verification', false) && ! $actor->staffProfile?->activeSignature) {
                throw ValidationException::withMessages(['signature' => 'Signature inahitajika kabla ya verification.']);
            }
            $result->update(['result_status' => LaboratoryResultStatus::Verified, 'verified_by' => $actor->id, 'verified_at' => now(), 'updated_by' => $actor->id]);
            $result->orderItem->update(['result_status' => 'verified', 'result_verified_at' => now()]);
            ActivityLog::query()->create(['user_id' => $actor->id, 'event' => 'result_verified', 'subject_type' => $result::class, 'subject_id' => $result->id]);
            return $result->refresh();
        });
    }

    public function returnForCorrection(LaboratoryResult $result, string $reason, $actor): LaboratoryResult
    {
        if (blank($reason)) {
            throw ValidationException::withMessages(['reason' => 'Sababu inahitajika.']);
        }
        $result->update(['result_status' => LaboratoryResultStatus::Draft, 'comments' => trim(($result->comments ? $result->comments."\n" : '').'Returned: '.$reason), 'updated_by' => $actor->id]);
        ActivityLog::query()->create(['user_id' => $actor->id, 'event' => 'result_returned_for_correction', 'subject_type' => $result::class, 'subject_id' => $result->id]);
        return $result->refresh();
    }
}
