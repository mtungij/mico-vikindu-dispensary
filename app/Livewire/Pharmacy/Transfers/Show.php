<?php

namespace App\Livewire\Pharmacy\Transfers;

use App\Models\StockTransfer;
use App\Services\StockTransferService;
use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Show extends Component { public StockTransfer $stockTransfer; public function mount(StockTransfer $stockTransfer): void { Gate::authorize('pharmacy.transfer-stock'); abort_unless($stockTransfer->facility_id === currentFacility()?->id, 404); $this->stockTransfer = $stockTransfer; } public function dispatchTransfer(StockTransferService $service): void { $service->dispatch($this->stockTransfer, auth()->user()); Notifier::success('stock_transfers.dispatched'); } public function receive(StockTransferService $service): void { $service->receive($this->stockTransfer, auth()->user()); Notifier::success('stock_transfers.received'); } public function render(): View { return view('livewire.pharmacy.transfers.show', ['transfer' => $this->stockTransfer->load(['fromLocation', 'toLocation', 'items.medicine', 'items.batch'])])->layout('components.layouts.app', ['title' => $this->stockTransfer->transfer_number, 'description' => 'Transfer details.']); } }
