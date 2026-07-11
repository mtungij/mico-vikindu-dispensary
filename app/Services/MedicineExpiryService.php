<?php

namespace App\Services;

use App\Enums\MedicineBatchStatus;
use App\Models\MedicineBatch;

class MedicineExpiryService
{
    public function getExpiredBatches() { return MedicineBatch::query()->forCurrentFacility()->whereNotNull('expiry_date')->whereDate('expiry_date', '<', today())->get(); }
    public function getExpiringBatches(int $days = 90) { return MedicineBatch::query()->forCurrentFacility()->whereNotNull('expiry_date')->whereBetween('expiry_date', [today(), today()->addDays($days)])->where('available_quantity', '>', 0)->get(); }
    public function refreshBatchStatuses(): int
    {
        return MedicineBatch::query()->whereNotNull('expiry_date')->whereDate('expiry_date', '<', today())->where('status', MedicineBatchStatus::Active)->update(['status' => MedicineBatchStatus::Expired]);
    }
    public function quarantineExpiredBatches(): int { return $this->refreshBatchStatuses(); }
    public function calculateExpiryRiskValue(int $days = 90): float { return (float) $this->getExpiringBatches($days)->sum(fn ($batch) => (float) $batch->available_quantity * (float) $batch->unit_cost); }
}
