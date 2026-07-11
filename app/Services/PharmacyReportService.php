<?php

namespace App\Services;

use App\Models\DispensingItem;
use App\Models\MedicineBatch;
use App\Models\StockMovement;

class PharmacyReportService
{
    public function revenueToday(): float { return (float) DispensingItem::query()->whereHas('dispensing', fn ($q) => $q->forCurrentFacility()->whereDate('dispensed_at', today()))->sum('total_amount'); }
    public function profitToday(): float { return (float) DispensingItem::query()->whereHas('dispensing', fn ($q) => $q->forCurrentFacility()->whereDate('dispensed_at', today()))->with('allocations')->get()->sum(fn ($item) => (float) $item->total_amount - $item->allocations->sum(fn ($a) => (float) $a->quantity * (float) $a->unit_cost_snapshot)); }
    public function stockMovements() { return StockMovement::query()->forCurrentFacility()->with(['medicine', 'batch', 'location', 'performer'])->latest('occurred_at'); }
    public function batches() { return MedicineBatch::query()->forCurrentFacility()->with(['medicine', 'location'])->latest(); }
}
