<?php

namespace App\Services;

use App\Models\Medicine;

class PharmacyReorderService
{
    public function getCurrentStock(Medicine $medicine): float { return (float) $medicine->batches()->sum('available_quantity'); }
    public function getAvailableStock(Medicine $medicine): float { return (float) $medicine->batches()->where('status', 'active')->sum('available_quantity'); }
    public function getLowStockMedicines() { return Medicine::query()->forCurrentFacility()->get()->filter(fn ($medicine) => $this->getAvailableStock($medicine) > 0 && $this->getAvailableStock($medicine) <= (float) $medicine->reorder_level); }
    public function getOutOfStockMedicines() { return Medicine::query()->forCurrentFacility()->get()->filter(fn ($medicine) => $this->getAvailableStock($medicine) <= 0); }
    public function getExcessStockMedicines() { return Medicine::query()->forCurrentFacility()->get()->filter(fn ($medicine) => $medicine->maximum_stock_level && $this->getAvailableStock($medicine) > (float) $medicine->maximum_stock_level); }
    public function suggestReorderQuantity(Medicine $medicine): ?float { return $medicine->maximum_stock_level ? max(0, (float) $medicine->maximum_stock_level - $this->getAvailableStock($medicine)) : null; }
}
