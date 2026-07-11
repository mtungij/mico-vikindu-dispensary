<?php

namespace App\Livewire\Pharmacy\Receipts;

use App\Models\PurchaseReceipt;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Index extends Component { public function mount(): void { Gate::authorize('pharmacy.receive-stock'); } public function render(): View { return view('livewire.pharmacy.receipts.index', ['rows' => PurchaseReceipt::query()->forCurrentFacility()->with(['supplier', 'location'])->latest()->paginate(12)])->layout('components.layouts.app', ['title' => 'Stock Receiving', 'description' => 'Receipts na verification.']); } }
