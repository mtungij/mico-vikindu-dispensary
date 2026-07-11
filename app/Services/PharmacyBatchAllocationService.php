<?php

namespace App\Services;

use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\StockLocation;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PharmacyBatchAllocationService
{
    public function getAvailableBatches(Medicine $medicine, ?StockLocation $location = null): Collection
    {
        return MedicineBatch::query()
            ->where('facility_id', $medicine->facility_id)
            ->where('medicine_id', $medicine->id)
            ->when($location, fn ($q) => $q->where('stock_location_id', $location->id))
            ->dispensable()
            ->orderByRaw('expiry_date is null')
            ->orderBy('expiry_date')
            ->orderBy('received_at')
            ->get();
    }

    public function allocateFefo(Medicine $medicine, StockLocation $location, string $quantity): array
    {
        $remaining = (float) $quantity;
        $allocations = [];
        foreach ($this->getAvailableBatches($medicine, $location) as $batch) {
            if ($remaining <= 0) break;
            $take = min($remaining, (float) $batch->available_quantity);
            if ($take > 0) {
                $allocations[] = ['batch' => $batch, 'quantity' => $take];
                $remaining -= $take;
            }
        }
        if ($remaining > 0.0001) {
            throw ValidationException::withMessages(['stock' => "Stock haitoshi kwa {$medicine->name}."]);
        }
        return $allocations;
    }

    public function validateBatchAvailability(MedicineBatch $batch, string $quantity): void
    {
        if (! $batch->newQuery()->whereKey($batch->id)->dispensable()->exists() || (float) $batch->available_quantity < (float) $quantity) {
            throw ValidationException::withMessages(['batch' => 'Batch haipatikani au stock haitoshi.']);
        }
    }
}
