<?php

namespace App\Livewire\Pharmacy;

use App\Models\Medicine;
use App\Models\StockLocation;
use App\Services\StockMovementService;
use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class OpeningStock extends Component
{
    public array $data = ['quantity' => 0, 'unit_cost' => 0, 'batch_number' => '', 'expiry_date' => null, 'reason' => 'Opening stock']; public ?int $medicine_id = null; public ?int $stock_location_id = null;
    public function mount(): void { Gate::authorize('pharmacy.opening-stock'); }
    public function post(StockMovementService $stock): void { $medicine = Medicine::query()->forCurrentFacility()->findOrFail($this->medicine_id); $location = StockLocation::query()->forCurrentFacility()->findOrFail($this->stock_location_id); $stock->openingStock($medicine, $location, $this->data, auth()->user()); Notifier::success('inventory.opening_stock_posted'); }
    public function render(): View { return view('livewire.pharmacy.opening-stock', ['medicines' => Medicine::query()->forCurrentFacility()->get(), 'locations' => StockLocation::query()->forCurrentFacility()->get()])->layout('components.layouts.app', ['title' => 'Opening Stock', 'description' => 'Weka stock ya mwanzo kwa batch na movement ledger.']); }
}
