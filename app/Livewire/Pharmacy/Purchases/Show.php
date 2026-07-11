<?php

namespace App\Livewire\Pharmacy\Purchases;

use App\Models\PurchaseOrder;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Show extends Component { public PurchaseOrder $purchaseOrder; public function mount(PurchaseOrder $purchaseOrder): void { Gate::authorize('pharmacy.receive-stock'); abort_unless($purchaseOrder->facility_id === currentFacility()?->id, 404); $this->purchaseOrder = $purchaseOrder; } public function render(): View { return view('livewire.pharmacy.purchases.show', ['order' => $this->purchaseOrder->load(['supplier', 'items.medicine'])])->layout('components.layouts.app', ['title' => $this->purchaseOrder->purchase_order_number, 'description' => 'Purchase order details.']); } }
