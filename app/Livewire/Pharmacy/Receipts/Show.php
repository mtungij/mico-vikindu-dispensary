<?php

namespace App\Livewire\Pharmacy\Receipts;

use App\Models\PurchaseReceipt;
use App\Services\StockReceivingService;
use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Show extends Component { public PurchaseReceipt $purchaseReceipt; public function mount(PurchaseReceipt $purchaseReceipt): void { Gate::authorize('pharmacy.receive-stock'); abort_unless($purchaseReceipt->facility_id === currentFacility()?->id, 404); $this->purchaseReceipt = $purchaseReceipt; } public function verify(StockReceivingService $service): void { Gate::authorize('pharmacy.verify-receipt'); $service->verify($this->purchaseReceipt, auth()->user()); Notifier::success('inventory.receipt_verified'); } public function render(): View { return view('livewire.pharmacy.receipts.show', ['receipt' => $this->purchaseReceipt->load(['supplier', 'location', 'items.medicine'])])->layout('components.layouts.app', ['title' => $this->purchaseReceipt->receipt_number, 'description' => 'Receiving note.']); } }
