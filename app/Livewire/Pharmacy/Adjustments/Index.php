<?php

namespace App\Livewire\Pharmacy\Adjustments;

use App\Models\StockAdjustment;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Index extends Component { public function mount(): void { Gate::authorize('pharmacy.adjust-stock'); } public function render(): View { return view('livewire.pharmacy.adjustments.index', ['rows' => StockAdjustment::query()->forCurrentFacility()->with('location')->latest()->paginate(12)])->layout('components.layouts.app', ['title' => 'Stock Adjustments', 'description' => 'Marekebisho ya stock kwa movement ledger.']); } }
