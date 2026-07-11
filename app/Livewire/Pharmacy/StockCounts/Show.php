<?php

namespace App\Livewire\Pharmacy\StockCounts;

use App\Models\StockCount;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Show extends Component { public StockCount $stockCount; public function mount(StockCount $stockCount): void { Gate::authorize('pharmacy.stock-count'); abort_unless($stockCount->facility_id === currentFacility()?->id, 404); $this->stockCount = $stockCount; } public function render(): View { return view('livewire.pharmacy.stock-counts.show', ['count' => $this->stockCount->load(['location', 'items'])])->layout('components.layouts.app', ['title' => $this->stockCount->count_number, 'description' => 'Stock count sheet.']); } }
