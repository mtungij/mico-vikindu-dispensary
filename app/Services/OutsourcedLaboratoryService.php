<?php

namespace App\Services;

use App\Enums\OutsourcedLaboratoryStatus;
use App\Models\ActivityLog;
use App\Models\LaboratoryOrderItem;
use App\Models\OutsourcedLaboratoryRequest;

class OutsourcedLaboratoryService
{
    public function prepare(LaboratoryOrderItem $item, array $data, $actor): OutsourcedLaboratoryRequest
    {
        $request = OutsourcedLaboratoryRequest::query()->create([
            'facility_id' => $item->order->facility_id,
            'laboratory_order_item_id' => $item->id,
            'external_provider_name' => $data['external_provider_name'],
            'external_reference_number' => $data['external_reference_number'] ?? null,
            'expected_at' => $data['expected_at'] ?? null,
            'status' => OutsourcedLaboratoryStatus::Prepared,
            'notes' => $data['notes'] ?? null,
            'created_by' => $actor->id,
        ]);
        ActivityLog::query()->create(['user_id' => $actor->id, 'event' => 'outsourced_test_sent', 'subject_type' => $request::class, 'subject_id' => $request->id]);
        return $request;
    }
}
