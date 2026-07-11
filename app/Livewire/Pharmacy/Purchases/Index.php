<?php

namespace App\Livewire\Pharmacy\Purchases;

use App\Models\PurchaseOrder;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Index extends Component { public function mount(): void { Gate::authorize('pharmacy.receive-stock'); } public function render(): View { return view('livewire.pharmacy.purchases.index', ['rows' => PurchaseOrder::query()->forCurrentFacility()->with('supplier')->latest()->paginate(12)])->layout('components.layouts.app', ['title' => 'Purchase Orders', 'description' => 'Purchase order foundation.']); } }
