<?php

namespace App\Services;

use App\Enums\ClinicalOrderStatus;
use App\Enums\LaboratorySampleStatus;
use App\Models\ActivityLog;
use App\Models\LaboratoryOrder;
use App\Models\LaboratorySample;
use App\Models\LaboratorySampleRejectionReason;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class LaboratorySampleService
{
    public function __construct(
        private readonly LaboratorySampleNumberService $numbers,
        private readonly LaboratoryPaymentGuard $paymentGuard,
    ) {}

    public function collectSample(LaboratoryOrder $order, array $data, $actor, bool $accept = false): LaboratorySample
    {
        return DB::transaction(function () use ($order, $data, $actor, $accept) {
            abort_unless($actor->can('laboratory.collect-sample'), 403, 'Huna ruhusa ya kukusanya sampuli.');
            if ($accept) {
                abort_unless($actor->can('laboratory.accept-sample'), 403, 'Huna ruhusa ya kukubali sampuli.');
            }

            $order = LaboratoryOrder::query()->lockForUpdate()->findOrFail($order->id);
            abort_unless(
                $order->facility_id === currentFacility()?->id && $actor->belongsToCurrentFacility(),
                403,
                'Order hii ni ya facility nyingine.',
            );
            if ($order->items()->whereNotNull('sample_id')->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['order_item_ids' => 'Sampuli ya kipimo hiki tayari imekusanywa.']);
            }
            $this->paymentGuard->ensureProcessable($order, $actor, 'collect_sample');
            $eligibleStatuses = [ClinicalOrderStatus::Ordered];
            if ($actor->can('laboratory.override-payment')) {
                $eligibleStatuses[] = ClinicalOrderStatus::AwaitingPayment;
            }
            if (! in_array($order->status, $eligibleStatuses, true)) {
                throw ValidationException::withMessages([
                    'order' => "Order hii haipo tayari kwa ukusanyaji wa sampuli (status: {$order->status->value}).",
                ]);
            }
            $itemIds = collect($data['order_item_ids'] ?? [])
                ->map(fn ($id): int => (int) $id)
                ->filter()
                ->unique()
                ->values();
            if ($itemIds->isEmpty()) {
                $itemIds = $order->items()->pluck('id')->map(fn ($id): int => (int) $id)->values();
            }
            if ($itemIds->isEmpty()) {
                $storedItems = $order->items()->withTrashed()->get(['id', 'status', 'deleted_at']);
                $message = $storedItems->isEmpty()
                    ? 'Order hii haina vipimo vilivyohifadhiwa. Tafadhali futa order hii na daktari aagize vipimo upya.'
                    : 'Vipimo vya order hii vimefutwa.';
                $this->collectionError($order, $actor, 'order_item_ids', $message, $storedItems);
            }
            $items = $order->items()->whereIn('id', $itemIds)->with('laboratoryTest.specimenType')->lockForUpdate()->get();
            if ($items->count() !== $itemIds->count()) {
                $this->collectionError($order, $actor, 'order_item_ids', 'Kitambulisho cha kipimo hakilingani na order hii.', $items);
            }
            if ($items->contains(fn ($item): bool => $item->status === 'cancelled')) {
                $this->collectionError($order, $actor, 'order_item_ids', 'Vipimo vya order hii vimefutwa.', $items);
            }
            if ($items->contains(fn ($item): bool => $item->sample_id !== null)) {
                $this->collectionError($order, $actor, 'order_item_ids', 'Vipimo vya order hii tayari vimekusanywa.', $items);
            }
            $eligibleItemStatuses = ['ordered', 'awaiting_payment', 'ready_for_collection', 'pending_collection'];
            if ($items->contains(fn ($item): bool => ! in_array($item->status, $eligibleItemStatuses, true))) {
                $this->collectionError($order, $actor, 'order_item_ids', 'Hakuna kipimo kilicho tayari kukusanywa sampuli.', $items);
            }
            $firstItem = $items->firstOrFail();
            if (! $firstItem->laboratory_test_id) {
                throw ValidationException::withMessages(['laboratory_test_id' => 'Test haijasanidiwa kwa service hii.']);
            }
            if ($items->contains(fn ($item): bool => ! ($item->specimen_type_id ?? $item->laboratoryTest?->specimen_type_id))) {
                $this->collectionError($order, $actor, 'specimen_type_id', 'Aina ya sampuli haijawekwa kwenye huduma ya kipimo.', $items);
            }
            $specimenTypeId = $data['specimen_type_id'] ?? $firstItem->specimen_type_id ?? $firstItem->laboratoryTest?->specimen_type_id;
            if ($items->contains(function ($item) use ($specimenTypeId): bool {
                $required = $item->specimen_type_id ?? $item->laboratoryTest?->specimen_type_id;

                return (int) $required !== (int) $specimenTypeId;
            })) {
                $this->collectionError($order, $actor, 'specimen_type_id', 'Vipimo vilivyochaguliwa vinahitaji specimen tofauti; kusanya kila specimen kivyake.', $items);
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
                'accepted_by' => $accept ? $actor->id : null,
                'accepted_at' => $accept ? now() : null,
                'collection_location' => $data['collection_location'] ?? null,
                'volume_collected' => $data['volume_collected'] ?? null,
                'volume_unit' => $data['volume_unit'] ?? null,
                'collection_notes' => $data['collection_notes'] ?? null,
                'sample_status' => $accept ? LaboratorySampleStatus::Accepted : LaboratorySampleStatus::Collected,
                'quality_status' => $accept ? 'acceptable' : null,
                'created_by' => $actor->id,
            ]);
            $sample->update(['barcode_value' => $sample->sample_number]);
            $this->attachOrderItems($sample, $itemIds->all());
            $order->items()->whereIn('id', $itemIds)->update(['sample_id' => $sample->id, 'status' => $accept ? 'sample_accepted' : 'sample_collected']);
            $order->update(['status' => $accept ? ClinicalOrderStatus::Processing : ClinicalOrderStatus::SamplePending, 'updated_by' => $actor->id]);
            $this->audit($actor, $accept ? 'sample_accepted' : 'sample_collected', $sample);
            if ($accept) {
                $this->auditOrder($actor, 'laboratory_processing_started', $order);
            }

            return $sample->refresh();
        });
    }

    public function receiveSample(LaboratorySample $sample, $actor): LaboratorySample
    {
        abort_unless($actor->can('laboratory.receive-sample'), 403);
        $this->paymentGuard->ensureProcessable($sample->order, $actor, 'receive_sample');
        $sample->update(['sample_status' => LaboratorySampleStatus::Received, 'received_by' => $actor->id, 'received_at' => now(), 'updated_by' => $actor->id]);
        $this->audit($actor, 'sample_received', $sample);

        return $sample->refresh();
    }

    public function acceptSample(LaboratorySample $sample, $actor): LaboratorySample
    {
        abort_unless($actor->can('laboratory.accept-sample'), 403);
        $this->paymentGuard->ensureProcessable($sample->order, $actor, 'accept_sample');
        $sample->update(['sample_status' => LaboratorySampleStatus::Accepted, 'quality_status' => 'acceptable', 'accepted_by' => $actor->id, 'accepted_at' => now(), 'updated_by' => $actor->id]);
        $this->audit($actor, 'sample_accepted', $sample);
        $this->auditOrder($actor, 'laboratory_processing_started', $sample->order);

        return $sample->refresh();
    }

    public function rejectSample(LaboratorySample $sample, LaboratorySampleRejectionReason $reason, string $notes, $actor): LaboratorySample
    {
        if (blank($notes)) {
            throw ValidationException::withMessages(['rejection_notes' => 'Maelezo ya rejection yanahitajika.']);
        }
        $sample->update(['sample_status' => $reason->requires_recollection ? LaboratorySampleStatus::RecollectionRequired : LaboratorySampleStatus::Rejected, 'quality_status' => 'other', 'rejection_reason_id' => $reason->id, 'rejection_notes' => $notes, 'rejected_by' => $actor->id, 'rejected_at' => now(), 'updated_by' => $actor->id]);
        $this->audit($actor, $reason->requires_recollection ? 'recollection_requested' : 'sample_rejected', $sample);

        return $sample->refresh();
    }

    public function requestRecollection(LaboratorySample $sample, string $reason, $actor): LaboratorySample
    {
        $sample->update(['sample_status' => LaboratorySampleStatus::RecollectionRequired, 'rejection_notes' => $reason, 'updated_by' => $actor->id]);
        $this->audit($actor, 'recollection_requested', $sample);

        return $sample->refresh();
    }

    public function disposeSample(LaboratorySample $sample, $actor): LaboratorySample
    {
        $sample->update(['sample_status' => LaboratorySampleStatus::Disposed, 'disposed_by' => $actor->id, 'disposed_at' => now(), 'updated_by' => $actor->id]);
        $this->audit($actor, 'sample_disposed', $sample);

        return $sample->refresh();
    }

    public function attachOrderItems(LaboratorySample $sample, array $orderItemIds): void
    {
        foreach ($orderItemIds as $id) {
            $sample->items()->firstOrCreate(['laboratory_order_item_id' => $id], ['status' => 'attached']);
        }
    }

    private function audit($actor, string $event, LaboratorySample $sample): void
    {
        ActivityLog::query()->create(['user_id' => $actor->id, 'event' => $event, 'subject_type' => $sample::class, 'subject_id' => $sample->id, 'new_values' => ['facility_id' => $sample->facility_id, 'visit_id' => $sample->visit_id, 'laboratory_order_id' => $sample->laboratory_order_id]]);
    }

    private function auditOrder($actor, string $event, LaboratoryOrder $order): void
    {
        ActivityLog::query()->create(['user_id' => $actor->id, 'event' => $event, 'subject_type' => $order::class, 'subject_id' => $order->id, 'new_values' => ['facility_id' => $order->facility_id, 'visit_id' => $order->visit_id, 'laboratory_order_id' => $order->id]]);
    }

    private function collectionError(LaboratoryOrder $order, $actor, string $field, string $message, $items): never
    {
        Log::warning('Laboratory sample collection rejected.', [
            'laboratory_order_id' => $order->id,
            'facility_id' => $order->facility_id,
            'current_facility_id' => currentFacility()?->id,
            'user_id' => $actor->id,
            'order_status' => $order->status->value,
            'payment_status' => $order->payment_status->value,
            'item_count' => $order->items()->withTrashed()->count(),
            'eligible_item_count' => collect($items)->filter(fn ($item): bool => ! $item->deleted_at && in_array($item->status, ['ordered', 'awaiting_payment', 'ready_for_collection', 'pending_collection'], true) && ! $item->sample_id)->count(),
            'items' => collect($items)->map(fn ($item): array => [
                'id' => $item->id,
                'status' => $item->status,
                'service_id' => $item->service_id ?? null,
                'specimen_type_id' => $item->specimen_type_id ?? null,
                'sample_id' => $item->sample_id ?? null,
                'deleted_at' => $item->deleted_at,
            ])->values()->all(),
        ]);

        throw ValidationException::withMessages([$field => $message]);
    }
}
