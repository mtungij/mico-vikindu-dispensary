<?php

namespace App\Services;

use App\Models\Medicine;
use App\Models\StockLocation;

class MedicineStockService
{
    public function currentStock(Medicine $medicine, ?StockLocation $location = null): float { return (float) $medicine->batches()->when($location, fn ($q) => $q->where('stock_location_id', $location->id))->sum('available_quantity'); }
    public function stockValue(?StockLocation $location = null): float { return (float) \App\Models\MedicineBatch::query()->forCurrentFacility()->when($location, fn ($q) => $q->where('stock_location_id', $location->id))->get()->sum(fn ($batch) => (float) $batch->available_quantity * (float) $batch->unit_cost); }
}
