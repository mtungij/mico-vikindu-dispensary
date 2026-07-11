<?php

namespace App\Livewire\Pharmacy\Receipts;

use App\Livewire\Forms\PurchaseReceiptForm;
use App\Models\Medicine;
use App\Models\StockLocation;
use App\Models\Supplier;
use App\Services\StockReceivingService;
use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Create extends Component { public PurchaseReceiptForm $form; public function mount(): void { Gate::authorize('pharmacy.receive-stock'); $this->form->items = [['medicine_id' => null, 'batch_number' => '', 'expiry_date' => null, 'quantity_received' => 0, 'unit_cost' => 0]]; } public function addLine(): void { $this->form->items[] = ['medicine_id' => null, 'batch_number' => '', 'expiry_date' => null, 'quantity_received' => 0, 'unit_cost' => 0]; } public function receive(StockReceivingService $service): void { $this->form->validate(); $receipt = $service->receive($this->form->normalize(), $this->form->items, auth()->user()); Notifier::success('inventory.stock_received'); $this->redirectRoute('pharmacy.receipts.show', $receipt); } public function render(): View { return view('livewire.pharmacy.receipts.create', ['suppliers' => Supplier::query()->forCurrentFacility()->get(), 'locations' => StockLocation::query()->forCurrentFacility()->where('is_receiving_location', true)->get(), 'medicines' => Medicine::query()->forCurrentFacility()->get()])->layout('components.layouts.app', ['title' => 'Receive Stock', 'description' => 'Pokea stock kwa batches na expiry.']); } }
