<?php

namespace App\Services;

use App\Enums\ClinicalOrderStatus;
use App\Enums\LaboratoryResultStatus;
use App\Models\LaboratoryOrder;

class LaboratoryOrderStatusService
{
    public function recalculate(LaboratoryOrder $order, $actor = null): LaboratoryOrder
    {
        $order->load('items');
        $items = $order->items->where('status', '!=', 'cancelled');
        if ($items->isEmpty()) {
            return $order;
        }

        $resultStatuses = $items->pluck('result_status')->filter();
        $allReleased = $items->every(fn ($item): bool => $item->result_status === LaboratoryResultStatus::Released->value || $item->status === 'completed');
        $allSubmitted = $items->every(fn ($item): bool => in_array($item->result_status, [
            LaboratoryResultStatus::PendingVerification->value,
            LaboratoryResultStatus::Verified->value,
            LaboratoryResultStatus::Released->value,
        ], true));
        $hasCollected = $items->contains(fn ($item): bool => $item->sample_id !== null || in_array($item->status, ['sample_collected', 'sample_accepted', 'processing'], true));
        $hasUncollected = $items->contains(fn ($item): bool => $item->sample_id === null && in_array($item->status, ['ordered', 'awaiting_payment', 'ready_for_collection', 'pending_collection'], true));

        $status = match (true) {
            $allReleased => ClinicalOrderStatus::Completed,
            $allSubmitted => ClinicalOrderStatus::ResultReady,
            $hasCollected => ClinicalOrderStatus::Processing,
            $hasUncollected && $order->payment_status->value === 'pending' => ClinicalOrderStatus::AwaitingPayment,
            $hasUncollected => ClinicalOrderStatus::Ordered,
            $resultStatuses->isNotEmpty() => ClinicalOrderStatus::Processing,
            default => $order->status,
        };

        $updates = ['status' => $status];
        if ($actor) {
            $updates['updated_by'] = $actor->id;
        }
        if ($status === ClinicalOrderStatus::Completed) {
            $updates['completed_at'] = $order->completed_at ?? now();
        }
        $order->update($updates);

        return $order->refresh();
    }
}
