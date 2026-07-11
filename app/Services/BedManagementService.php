<?php

namespace App\Services;

use App\Enums\BedAssignmentStatus;
use App\Enums\BedCleaningStatus;
use App\Enums\BedReservationStatus;
use App\Enums\BedStatus;
use App\Models\ActivityLog;
use App\Models\Bed;
use App\Models\BedAssignment;
use App\Models\BedCleaningRecord;
use App\Models\BedReservation;
use App\Models\ObservationAdmission;
use App\Models\ObservationRoom;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BedManagementService
{
    public function save(array $data, $actor, ?Bed $bed = null): Bed
    {
        $room = ObservationRoom::query()->where('facility_id', currentFacility()->id)->findOrFail($data['observation_room_id']);
        if ($bed && ($bed->status === BedStatus::Occupied) && in_array($data['status'] ?? null, [BedStatus::OutOfService->value, BedStatus::Blocked->value, 'out_of_service', 'blocked'], true)) throw ValidationException::withMessages(['status' => 'Kitanda kinatumika; hamisha au discharge kwanza.']);
        $payload = [...$data, 'facility_id' => $room->facility_id, 'updated_by' => $actor->id];
        $bed ? $bed->update($payload) : $bed = Bed::query()->create([...$payload, 'status' => $data['status'] ?? BedStatus::Available, 'current_cleaning_status' => $data['current_cleaning_status'] ?? BedCleaningStatus::Clean, 'created_by' => $actor->id]);
        ActivityLog::query()->create(['user_id' => $actor->id, 'event' => $bed->wasRecentlyCreated ? 'bed_created' : 'bed_updated', 'subject_type' => $bed::class, 'subject_id' => $bed->id]);
        return $bed->refresh();
    }

    public function getAvailableBeds(?int $roomId = null) { return Bed::query()->forCurrentFacility()->where('is_active', true)->where('status', BedStatus::Available)->when($roomId, fn ($q) => $q->where('observation_room_id', $roomId))->with('room')->get(); }

    public function reserveBed(Bed $bed, $patient, $visit, $actor, ?string $notes = null, ?\DateTimeInterface $expiresAt = null): BedReservation
    {
        return DB::transaction(function () use ($bed, $patient, $visit, $actor, $notes, $expiresAt): BedReservation {
            $bed = Bed::query()->where('facility_id', currentFacility()->id)->lockForUpdate()->findOrFail($bed->id);
            $this->ensureBedCanBeUsed($bed);
            if (BedReservation::query()->where('bed_id', $bed->id)->where('status', BedReservationStatus::Active)->exists()) throw ValidationException::withMessages(['bed' => 'Kitanda tayari kina reservation.']);
            $reservation = BedReservation::query()->create(['facility_id' => $bed->facility_id, 'patient_id' => $patient->id, 'visit_id' => $visit->id, 'bed_id' => $bed->id, 'reserved_by' => $actor->id, 'reserved_at' => now(), 'expires_at' => $expiresAt ?? now()->addMinutes(30), 'status' => BedReservationStatus::Active, 'notes' => $notes]);
            $bed->update(['status' => BedStatus::Reserved, 'updated_by' => $actor->id]);
            ActivityLog::query()->create(['user_id' => $actor->id, 'event' => 'bed_reserved', 'subject_type' => $reservation::class, 'subject_id' => $reservation->id]);
            return $reservation;
        });
    }

    public function assignBed(ObservationAdmission $admission, Bed $bed, $actor, ?BedReservation $reservation = null): BedAssignment
    {
        return DB::transaction(function () use ($admission, $bed, $actor, $reservation): BedAssignment {
            $bed = Bed::query()->with('room')->where('facility_id', $admission->facility_id)->lockForUpdate()->findOrFail($bed->id);
            if ($bed->status !== BedStatus::Available && ! ($bed->status === BedStatus::Reserved && $reservation?->patient_id === $admission->patient_id)) throw ValidationException::withMessages(['bed' => 'Kitanda hakipo wazi.']);
            $this->validateBedCompatibility($admission, $bed);
            if (BedAssignment::query()->where('bed_id', $bed->id)->where('assignment_status', BedAssignmentStatus::Active)->exists()) throw ValidationException::withMessages(['bed' => 'Kitanda kina active assignment.']);
            $assignment = BedAssignment::query()->create(['facility_id' => $admission->facility_id, 'observation_admission_id' => $admission->id, 'patient_id' => $admission->patient_id, 'bed_id' => $bed->id, 'room_id' => $bed->observation_room_id, 'assigned_by' => $actor->id, 'assigned_at' => now(), 'assignment_status' => BedAssignmentStatus::Active]);
            $bed->update(['status' => BedStatus::Occupied, 'updated_by' => $actor->id]);
            $admission->update(['current_bed_id' => $bed->id, 'current_room_id' => $bed->observation_room_id, 'status' => 'under_observation', 'updated_by' => $actor->id]);
            $reservation?->update(['status' => BedReservationStatus::Fulfilled, 'admission_id' => $admission->id]);
            ActivityLog::query()->create(['user_id' => $actor->id, 'event' => 'bed_assigned', 'subject_type' => $assignment::class, 'subject_id' => $assignment->id]);
            return $assignment;
        });
    }

    public function transferBed(ObservationAdmission $admission, Bed $destination, $actor, string $reason): BedAssignment
    {
        if (blank($reason)) throw ValidationException::withMessages(['reason' => 'Sababu ya transfer inahitajika.']);
        return DB::transaction(function () use ($admission, $destination, $actor, $reason): BedAssignment {
            $old = $admission->activeAssignment()->lockForUpdate()->firstOrFail();
            if ($old->bed_id === $destination->id) throw ValidationException::withMessages(['bed' => 'Chagua kitanda tofauti.']);
            $oldBed = Bed::query()->lockForUpdate()->findOrFail($old->bed_id);
            $old->update(['assignment_status' => BedAssignmentStatus::Transferred, 'released_by' => $actor->id, 'released_at' => now(), 'transfer_reason' => $reason]);
            $oldBed->update(['status' => BedStatus::Cleaning, 'current_cleaning_status' => BedCleaningStatus::NeedsCleaning, 'updated_by' => $actor->id]);
            $this->requestCleaning($oldBed, $admission, $actor, 'routine');
            $new = $this->assignBed($admission, $destination, $actor);
            ActivityLog::query()->create(['user_id' => $actor->id, 'event' => 'bed_transferred', 'subject_type' => $admission::class, 'subject_id' => $admission->id]);
            return $new;
        });
    }

    public function releaseBed(ObservationAdmission $admission, $actor, string $cleaningType = 'discharge'): void
    {
        DB::transaction(function () use ($admission, $actor, $cleaningType): void {
            $assignment = $admission->activeAssignment()->lockForUpdate()->first();
            if (! $assignment) return;
            $bed = Bed::query()->lockForUpdate()->findOrFail($assignment->bed_id);
            $assignment->update(['assignment_status' => BedAssignmentStatus::Released, 'released_by' => $actor->id, 'released_at' => now()]);
            $bed->update(['status' => BedStatus::Cleaning, 'current_cleaning_status' => BedCleaningStatus::NeedsCleaning, 'updated_by' => $actor->id]);
            $this->requestCleaning($bed, $admission, $actor, $cleaningType);
            ActivityLog::query()->create(['user_id' => $actor->id, 'event' => 'bed_released', 'subject_type' => $bed::class, 'subject_id' => $bed->id]);
        });
    }

    public function markCleaning(Bed $bed, $actor): Bed { $bed->update(['status' => BedStatus::Cleaning, 'current_cleaning_status' => BedCleaningStatus::CleaningInProgress, 'updated_by' => $actor->id]); ActivityLog::query()->create(['user_id'=>$actor->id,'event'=>'bed_cleaning_started','subject_type'=>$bed::class,'subject_id'=>$bed->id]); return $bed->refresh(); }
    public function markAvailable(Bed $bed, $actor): Bed { $bed->update(['status' => BedStatus::Available, 'current_cleaning_status' => BedCleaningStatus::Clean, 'last_cleaned_at' => now(), 'updated_by' => $actor->id]); ActivityLog::query()->create(['user_id'=>$actor->id,'event'=>'bed_cleaning_completed','subject_type'=>$bed::class,'subject_id'=>$bed->id]); return $bed->refresh(); }
    public function validateBedCompatibility(ObservationAdmission $admission, Bed $bed): void { if ($admission->isolation_required && ! ($bed->bed_type?->value === 'isolation' || $bed->room?->isolation_room)) throw ValidationException::withMessages(['bed' => 'Admission inahitaji isolation bed/room.']); }
    private function ensureBedCanBeUsed(Bed $bed): void { if (! $bed->is_active || $bed->status !== BedStatus::Available) throw ValidationException::withMessages(['bed' => 'Kitanda hakipo wazi.']); }
    private function requestCleaning(Bed $bed, ObservationAdmission $admission, $actor, string $type): void { BedCleaningRecord::query()->create(['facility_id'=>$bed->facility_id,'bed_id'=>$bed->id,'observation_admission_id'=>$admission->id,'cleaning_type'=>$type,'status'=>'requested','requested_at'=>now(),'requested_by'=>$actor->id]); }
}
