<?php

namespace App\Livewire\Pharmacy\Medicines;

use App\Models\Medicine;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class StockCard extends Component
{
    public Medicine $medicine;
    public function mount(Medicine $medicine): void { Gate::authorize('pharmacy.view-stock-card'); abort_unless($medicine->facility_id === currentFacility()?->id, 404); $this->medicine = $medicine; }
    public function render(): View { return view('livewire.pharmacy.medicines.stock-card', ['movements' => $this->medicine->movements()->with(['batch', 'location', 'performer'])->latest('occurred_at')->paginate(20)])->layout('components.layouts.app', ['title' => 'Stock Card', 'description' => $this->medicine->name]); }
}
