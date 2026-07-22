<?php

namespace App\Services;

use App\Enums\ClinicalOrderStatus;
use App\Enums\LaboratorySampleStatus;
use App\Models\ActivityLog;
use App\Models\LaboratoryOrder;
use App\Models\LaboratorySample;
use App\Models\LaboratorySampleRejectionReason;
use Illuminate\Support\Facades\DB;
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
            $itemIds = collect($data['order_item_ids'] ?? $order->items()->pluck('id')->all())
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values();
            if ($itemIds->isEmpty()) {
                throw ValidationException::withMessages(['order_item_ids' => 'Order haina vipimo vya sample.']);
            }
            $items = $order->items()->whereIn('id', $itemIds)->with('laboratoryTest.specimenType')->lockForUpdate()->get();
            if ($items->count() !== $itemIds->count()) {
                throw ValidationException::withMessages(['order_item_ids' => 'Kila kipimo kilichochaguliwa lazima kiwe kwenye order hii.']);
            }
            if ($items->contains(fn ($item): bool => $item->status === 'cancelled')) {
                throw ValidationException::withMessages(['order_item_ids' => 'Kipimo hiki kimefutwa.']);
            }
            if ($items->contains(fn ($item): bool => $item->sample_id !== null)) {
                throw ValidationException::withMessages(['order_item_ids' => 'Sampuli ya kipimo hiki tayari imekusanywa.']);
            }
            $firstItem = $items->firstOrFail();
            if (! $firstItem->laboratory_test_id) {
                throw ValidationException::withMessages(['laboratory_test_id' => 'Test haijasanidiwa kwa service hii.']);
            }
            $specimenTypeId = $data['specimen_type_id'] ?? $firstItem->specimen_type_id ?? $firstItem->laboratoryTest?->specimen_type_id;
            if (! $specimenTypeId) {
                throw ValidationException::withMessages(['specimen_type_id' => 'Specimen inayohitajika haijawekwa.']);
            }
            if ($items->contains(function ($item) use ($specimenTypeId): bool {
                $required = $item->specimen_type_id ?? $item->laboratoryTest?->specimen_type_id;

                return ! $required || (int) $required !== (int) $specimenTypeId;
            })) {
                throw ValidationException::withMessages(['specimen_type_id' => 'Vipimo vilivyochaguliwa vinahitaji specimen tofauti; kusanya kila specimen kivyake.']);
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
}
