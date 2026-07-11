<?php

namespace App\Livewire\Pharmacy;

use App\Models\Prescription;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class PrescriptionShow extends Component
{
    public Prescription $prescription;
    public function mount(Prescription $prescription): void { Gate::authorize('pharmacy.view-prescription'); abort_unless($prescription->facility_id === currentFacility()?->id, 404); $this->prescription = $prescription; }
    public function render(): View { return view('livewire.pharmacy.prescription-show', ['prescription' => $this->prescription->load(['patient', 'visit.invoice', 'encounter.provider', 'items.medicine.batches', 'dispensings.items'])])->layout('components.layouts.app', ['title' => $this->prescription->prescription_number, 'description' => 'Prescription review, allergies, stock availability na dispensing status.']); }
}
