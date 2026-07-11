<?php

namespace App\Services;

use App\Enums\ServiceType;
use App\Models\ActivityLog;
use App\Models\Medicine;
use App\Models\Service;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MedicineCatalogService
{
    public function createMedicine(array $data, $actor): Medicine
    {
        return DB::transaction(function () use ($data, $actor) {
            if (! empty($data['service_id'])) {
                $service = Service::query()->where('facility_id', currentFacility()->id)->findOrFail($data['service_id']);
                if ($service->service_type !== ServiceType::Medicine) {
                    throw ValidationException::withMessages(['service_id' => 'Service lazima iwe ya type medicine.']);
                }
            }
            $medicine = Medicine::query()->create([...$data, 'facility_id' => currentFacility()->id, 'code' => str($data['code'])->upper(), 'created_by' => $actor->id]);
            ActivityLog::query()->create(['user_id' => $actor->id, 'event' => 'medicine_created', 'subject_type' => $medicine::class, 'subject_id' => $medicine->id]);
            return $medicine;
        });
    }
}
