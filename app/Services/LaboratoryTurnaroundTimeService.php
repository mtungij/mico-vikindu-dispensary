<?php

namespace App\Services;

use App\Models\LaboratoryOrder;
use App\Models\LaboratoryOrderItem;
use App\Models\Facility;
use App\Models\LaboratoryTest;

class LaboratoryTurnaroundTimeService
{
    public function calculateForOrderItem(LaboratoryOrderItem $item): array
    {
        $order = $item->order;
        $sample = $item->sample;
        $result = $item->results()->latest('result_version')->first();
        return [
            'order_to_collection_minutes' => $sample?->collected_at ? $order->ordered_at->diffInMinutes($sample->collected_at) : null,
            'collection_to_entry_minutes' => $sample?->collected_at && $result?->entered_at ? $sample->collected_at->diffInMinutes($result->entered_at) : null,
            'entry_to_verification_minutes' => $result?->entered_at && $result?->verified_at ? $result->entered_at->diffInMinutes($result->verified_at) : null,
            'verification_to_release_minutes' => $result?->verified_at && $result?->released_at ? $result->verified_at->diffInMinutes($result->released_at) : null,
            'order_to_release_minutes' => $result?->released_at ? $order->ordered_at->diffInMinutes($result->released_at) : null,
        ];
    }

    public function calculateForOrder(LaboratoryOrder $order): array
    {
        return ['order_to_release_minutes' => $order->completed_at ? $order->ordered_at->diffInMinutes($order->completed_at) : null];
    }

    public function getAverageByTest(LaboratoryTest $test): ?float { return null; }
    public function getOverdueOrders() { return LaboratoryOrder::query()->forCurrentFacility()->whereIn('status', ['ordered', 'sample_pending', 'processing'])->get(); }
    public function compareToExpectedTurnaround(LaboratoryOrderItem $item): string { $tat = $this->calculateForOrderItem($item)['order_to_release_minutes']; $target = $item->laboratoryTest?->turnaround_time_minutes; return $tat && $target && $tat > $target ? 'overdue' : 'within_target'; }

    /**
     * @return array<string, int|float|null>
     */
    public function summary(?Facility $facility): array
    {
        $query = LaboratoryOrder::query()
            ->when($facility, fn ($query) => $query->where('facility_id', $facility->id));

        $released = (clone $query)->whereNotNull('completed_at')->get(['ordered_at', 'completed_at']);
        $average = $released->avg(fn (LaboratoryOrder $order) => $order->ordered_at?->diffInMinutes($order->completed_at));

        return [
            'orders_today' => (clone $query)->whereDate('ordered_at', today())->count(),
            'pending_orders' => (clone $query)->whereIn('status', ['ordered', 'sample_pending', 'processing'])->count(),
            'completed_today' => (clone $query)->whereDate('completed_at', today())->count(),
            'average_order_to_release_minutes' => $average ? round($average, 1) : null,
        ];
    }
}
