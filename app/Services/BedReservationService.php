<?php

namespace App\Services;

use App\Enums\BedReservationStatus;
use App\Enums\BedStatus;
use App\Models\ActivityLog;
use App\Models\BedReservation;
use Illuminate\Support\Facades\DB;

class BedReservationService
{
    public function expireReservations(): int
    {
        $count = 0;
        BedReservation::query()->where('status', BedReservationStatus::Active)->whereNotNull('expires_at')->where('expires_at', '<', now())->chunkById(100, function ($rows) use (&$count): void {
            foreach ($rows as $reservation) {
                DB::transaction(function () use ($reservation, &$count): void {
                    $bed = $reservation->bed()->lockForUpdate()->first();
                    $reservation->update(['status' => BedReservationStatus::Expired]);
                    if ($bed && ($bed->status?->value ?? $bed->status) === 'reserved' && ! $bed->activeAssignment()->exists()) $bed->update(['status' => BedStatus::Available]);
                    ActivityLog::query()->create(['user_id' => $reservation->reserved_by, 'event' => 'bed_reservation_expired', 'subject_type' => $reservation::class, 'subject_id' => $reservation->id]);
                    $count++;
                });
            }
        });
        return $count;
    }
}
