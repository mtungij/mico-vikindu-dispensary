<?php

namespace App\Services;

use App\Enums\LaboratorySampleStatus;
use App\Models\ActivityLog;
use App\Models\LaboratoryOrder;
use App\Models\LaboratorySample;
use App\Models\LaboratorySampleRejectionReason;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LaboratorySampleService
{
    public function __construct(private readonly LaboratorySampleNumberService $numbers) {}

    public function collectSample(LaboratoryOrder $order, array $data, $actor, bool $accept = false): LaboratorySample
    {
        return DB::transaction(function () use ($order, $data, $actor, $accept) {
            $itemIds = $data['order_item_ids'] ?? $order->items()->pluck('id')->all();
            if (empty($itemIds)) {
                throw ValidationException::withMessages(['order_item_ids' => 'Order haina vipimo vya sample.']);
            }
            $firstItem = $order->items()->whereIn('id', $itemIds)->with('laboratoryTest.specimenType')->firstOrFail();
            if (! $firstItem->laboratory_test_id) {
                throw ValidationException::withMessages(['laboratory_test_id' => 'Test haijasanidiwa kwa service hii.']);
            }
            $specimenTypeId = $data['specimen_type_id'] ?? $firstItem->specimen_type_id ?? $firstItem->laboratoryTest?->specimen_type_id;
            if (! $specimenTypeId) {
                throw ValidationException::withMessages(['specimen_type_id' => 'Specimen type inahitajika.']);
            }
            $sample = LaboratorySample::query()->create([
                'facility_id' => $order->facility_id,
                'laboratory_order_id' => $order->id,
                'patient_id' => $order->patient_id,
                'visit_id' => $order->visit_id,
                'sample_number' => $this->numbers->next($order->facility_id),
                'barcode_value' => null,
                'specimen_type_id' => $specimenTypeId,
                'container_type' => $data['container_type'] ?? $firstItem->laboratoryTest?->specimenType?->container_type,
                'collected_by' => $actor->id,
                'collected_at' => $data['collected_at'] ?? now(),
                'collection_location' => $data['collection_location'] ?? null,
                'volume_collected' => $data['volume_collected'] ?? null,
                'volume_unit' => $data['volume_unit'] ?? null,
                'collection_notes' => $data['collection_notes'] ?? null,
                'sample_status' => $accept ? LaboratorySampleStatus::Accepted : LaboratorySampleStatus::Collected,
                'quality_status' => $accept ? 'acceptable' : null,
                'created_by' => $actor->id,
            ]);
            $sample->update(['barcode_value' => $sample->sample_number]);
            $this->attachOrderItems($sample, $itemIds);
            $order->items()->whereIn('id', $itemIds)->update(['sample_id' => $sample->id, 'status' => $accept ? 'sample_accepted' : 'sample_collected']);
            $order->update(['status' => $accept ? 'processing' : 'sample_pending']);
            $this->audit($actor, $accept ? 'sample_accepted' : 'sample_collected', $sample);
            return $sample->refresh();
        });
    }

    public function receiveSample(LaboratorySample $sample, $actor): LaboratorySample { $sample->update(['sample_status' => LaboratorySampleStatus::Received, 'received_by' => $actor->id, 'received_at' => now(), 'updated_by' => $actor->id]); $this->audit($actor, 'sample_received', $sample); return $sample->refresh(); }
    public function acceptSample(LaboratorySample $sample, $actor): LaboratorySample { $sample->update(['sample_status' => LaboratorySampleStatus::Accepted, 'quality_status' => 'acceptable', 'updated_by' => $actor->id]); $this->audit($actor, 'sample_accepted', $sample); return $sample->refresh(); }

    public function rejectSample(LaboratorySample $sample, LaboratorySampleRejectionReason $reason, string $notes, $actor): LaboratorySample
    {
        if (blank($notes)) {
            throw ValidationException::withMessages(['rejection_notes' => 'Maelezo ya rejection yanahitajika.']);
        }
        $sample->update(['sample_status' => $reason->requires_recollection ? LaboratorySampleStatus::RecollectionRequired : LaboratorySampleStatus::Rejected, 'quality_status' => 'other', 'rejection_reason_id' => $reason->id, 'rejection_notes' => $notes, 'rejected_by' => $actor->id, 'rejected_at' => now(), 'updated_by' => $actor->id]);
        $this->audit($actor, $reason->requires_recollection ? 'recollection_requested' : 'sample_rejected', $sample);
        return $sample->refresh();
    }

    public function requestRecollection(LaboratorySample $sample, string $reason, $actor): LaboratorySample { $sample->update(['sample_status' => LaboratorySampleStatus::RecollectionRequired, 'rejection_notes' => $reason, 'updated_by' => $actor->id]); $this->audit($actor, 'recollection_requested', $sample); return $sample->refresh(); }
    public function disposeSample(LaboratorySample $sample, $actor): LaboratorySample { $sample->update(['sample_status' => LaboratorySampleStatus::Disposed, 'disposed_by' => $actor->id, 'disposed_at' => now(), 'updated_by' => $actor->id]); $this->audit($actor, 'sample_disposed', $sample); return $sample->refresh(); }

    public function attachOrderItems(LaboratorySample $sample, array $orderItemIds): void
    {
        foreach ($orderItemIds as $id) {
            $sample->items()->firstOrCreate(['laboratory_order_item_id' => $id], ['status' => 'attached']);
        }
    }

    private function audit($actor, string $event, LaboratorySample $sample): void
    {
        ActivityLog::query()->create(['user_id' => $actor->id, 'event' => $event, 'subject_type' => $sample::class, 'subject_id' => $sample->id]);
    }
}
