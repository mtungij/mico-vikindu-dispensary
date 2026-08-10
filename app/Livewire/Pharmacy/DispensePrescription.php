<?php

namespace App\Livewire\Pharmacy;

use App\Livewire\Forms\DispensingForm;
use App\Models\Prescription;
use App\Models\StockLocation;
use App\Services\PharmacyDispensingService;
use App\Services\PrescriptionService;
use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class DispensePrescription extends Component
{
    public Prescription $prescription;

    public DispensingForm $form;

    public array $terminalReasons = [];

    public function mount(Prescription $prescription): void
    {
        Gate::authorize('pharmacy.dispense');
        abort_unless($prescription->facility_id === currentFacility()?->id, 404);
        $this->prescription = $prescription;
        $this->form->stock_location_id = StockLocation::query()->forCurrentFacility()->where('is_dispensing_location', true)->first()?->id;
        $this->form->lines = $prescription->items()->get()->map(fn ($item) => ['prescription_item_id' => $item->id, 'medicine_id' => $item->medicine_id, 'quantity' => $item->remaining_quantity ?? $item->quantity])->all();
    }

    public function dispense(PharmacyDispensingService $service): void
    {
        $this->form->validate();
        $location = StockLocation::query()->forCurrentFacility()->findOrFail($this->form->stock_location_id);
        $dispensing = $service->dispense($this->prescription, $this->form->lines, $location, auth()->user(), $this->form->override_reason);
        $visitCompleted = $this->prescription->visit->refresh()->visit_status->value === 'completed';
        $message = match (true) {
            $dispensing->status->value !== 'completed' => 'Partial dispensing recorded. Pharmacy remains active.',
            $visitCompleted => 'Medicines dispensed successfully. All required services are completed. Visit closed.',
            default => 'Medicines dispensed successfully. Pharmacy completed. Patient still has pending services.',
        };
        Notifier::success($message);
        $this->redirectRoute('pharmacy.dispensings.labels', $dispensing);
    }

    public function declineItem(int $itemId, PrescriptionService $service): void
    {
        $reason = trim((string) ($this->terminalReasons[$itemId] ?? ''));
        $item = $this->prescription->items()->findOrFail($itemId);
        $service->terminallyDeclineItem($item, 'unavailable', $reason, auth()->user());
        $this->prescription = $this->prescription->refresh();
        $this->form->lines = $this->prescription->items()->whereNull('terminal_status')->get()->map(fn ($item) => ['prescription_item_id' => $item->id, 'medicine_id' => $item->medicine_id, 'quantity' => $item->remaining_quantity ?? $item->quantity])->all();
        Notifier::success('Unavailable medicine recorded and billing reconciled.');
    }

    public function render(): View
    {
        return view('livewire.pharmacy.dispense-prescription', ['prescription' => $this->prescription->load(['patient', 'items.medicine']), 'locations' => StockLocation::query()->forCurrentFacility()->where('is_dispensing_location', true)->get()])->layout('components.layouts.app', ['title' => 'Toa Dawa', 'description' => $this->prescription->prescription_number]);
    }
}
