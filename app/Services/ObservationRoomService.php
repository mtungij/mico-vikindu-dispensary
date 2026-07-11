<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\ObservationRoom;
use Illuminate\Validation\ValidationException;

class ObservationRoomService
{
    public function save(array $data, $actor, ?ObservationRoom $room = null): ObservationRoom
    {
        if (($data['capacity'] ?? 1) < 1) throw ValidationException::withMessages(['capacity' => 'Capacity isiwe chini ya 1.']);
        if (! empty($data['department_id']) && ! Department::query()->where('facility_id', currentFacility()->id)->whereKey($data['department_id'])->exists()) throw ValidationException::withMessages(['department_id' => 'Department si ya facility hii.']);
        if ($room && ($data['is_active'] ?? true) === false && $room->activeAdmissions()->exists()) throw ValidationException::withMessages(['is_active' => 'Room ina active admissions.']);
        $payload = [...$data, 'facility_id' => currentFacility()->id, 'updated_by' => $actor->id];
        $room ? $room->update($payload) : $room = ObservationRoom::query()->create([...$payload, 'created_by' => $actor->id]);
        ActivityLog::query()->create(['user_id' => $actor->id, 'event' => $room->wasRecentlyCreated ? 'observation_room_created' : 'observation_room_updated', 'subject_type' => $room::class, 'subject_id' => $room->id]);
        return $room->refresh();
    }
}
