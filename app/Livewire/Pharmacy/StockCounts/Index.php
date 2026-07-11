<?php

namespace App\Livewire\Pharmacy\StockCounts;

use App\Models\StockCount;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Index extends Component { public function mount(): void { Gate::authorize('pharmacy.stock-count'); } public function render(): View { return view('livewire.pharmacy.stock-counts.index', ['rows' => StockCount::query()->forCurrentFacility()->with('location')->latest()->paginate(12)])->layout('components.layouts.app', ['title' => 'Stock Counts', 'description' => 'Stock count na variance posting.']); } }
