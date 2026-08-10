<?php

namespace App\Livewire\Pharmacy;

use App\Models\Prescription;
use App\Services\PrescriptionBillingService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class Queue extends Component
{
    use WithPagination;

    public string $tab = 'ready';

    public string $search = '';

    public function mount(): void
    {
        Gate::authorize('pharmacy.view-queue');
    }

    public function render(PrescriptionBillingService $billing): View
    {
        $readyIds = $this->tab === 'ready'
            ? Prescription::query()
                ->forCurrentFacility()
                ->where('status', 'prescribed')
                ->whereHas('encounter', fn ($encounter) => $encounter->whereNotNull('completed_at'))
                ->whereHas('visit', fn ($visit) => $visit->whereNotIn('visit_status', ['referred', 'cancelled', 'discharged']))
                ->get()
                ->filter(fn (Prescription $prescription) => $billing->isCleared($prescription))
                ->modelKeys()
            : [];
        $rows = Prescription::query()->forCurrentFacility()->with(['patient', 'visit.invoice', 'encounter.provider'])->withCount('items')
            ->when($this->search, fn ($q) => $q->where('prescription_number', 'like', "%{$this->search}%")->orWhereHas('patient', fn ($p) => $p->where('first_name', 'like', "%{$this->search}%")->orWhere('last_name', 'like', "%{$this->search}%")->orWhere('patient_number', 'like', "%{$this->search}%")))
            ->when($this->tab === 'awaiting_payment', fn ($q) => $q->where('status', 'awaiting_payment'))
            ->when($this->tab === 'ready', fn ($q) => $q
                ->whereKey($readyIds))
            ->when($this->tab === 'partial', fn ($q) => $q->where('status', 'partially_dispensed'))
            ->when($this->tab === 'completed', fn ($q) => $q->where('status', 'dispensed'))
            ->when($this->tab === 'cancelled', fn ($q) => $q->where('status', 'cancelled'))
            ->latest('prescribed_at')->paginate(12);
        $rows->getCollection()->each(function (Prescription $prescription) use ($billing): void {
            $prescription->setAttribute('medicine_clearance', $billing->clearance($prescription));
        });

        return view('livewire.pharmacy.queue', ['prescriptions' => $rows])->layout('components.layouts.app', ['title' => 'Prescription Queue', 'description' => 'Foleni ya prescriptions kwa review na dispensing.']);
    }
}
