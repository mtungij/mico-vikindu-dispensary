<?php

namespace App\Livewire\Pharmacy\Transfers;

use App\Models\StockTransfer;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Index extends Component { public function mount(): void { Gate::authorize('pharmacy.transfer-stock'); } public function render(): View { return view('livewire.pharmacy.transfers.index', ['rows' => StockTransfer::query()->forCurrentFacility()->with(['fromLocation', 'toLocation'])->latest()->paginate(12)])->layout('components.layouts.app', ['title' => 'Stock Transfers', 'description' => 'Hamisha stock kati ya locations.']); } }
