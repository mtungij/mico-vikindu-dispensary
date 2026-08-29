<?php

namespace App\Livewire\Pharmacy;

use App\Livewire\Forms\DispensingForm;
use App\Models\Prescription;
use App\Models\StockLocation;
use App\Services\MedicineFinancialClearanceService;
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
        $this->refreshLines();
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

    public function updatedFormStockLocationId(): void
    {
        $this->refreshLines();
    }

    public function declineItem(int $itemId, PrescriptionService $service): void
    {
        $reason = trim((string) ($this->terminalReasons[$itemId] ?? ''));
        $item = $this->prescription->items()->findOrFail($itemId);
        $service->terminallyDeclineItem($item, 'unavailable', $reason, auth()->user());
        $this->prescription = $this->prescription->refresh();
        $this->refreshLines();
        Notifier::success('Unavailable medicine recorded and billing reconciled.');
    }

    public function render(MedicineFinancialClearanceService $clearance): View
    {
        $prescription = $this->prescription->load(['patient', 'items.invoiceItem', 'items.medicine.dispensingUnit']);
        $financialRows = $prescription->items->mapWithKeys(fn ($item) => [$item->id => $clearance->forItem($item)])->all();

        return view('livewire.pharmacy.dispense-prescription', [
            'prescription' => $prescription,
            'financialRows' => $financialRows,
            'locations' => StockLocation::query()->forCurrentFacility()->where('is_dispensing_location', true)->get(),
        ])->layout('components.layouts.app', ['title' => 'Toa Dawa', 'description' => $this->prescription->prescription_number]);
    }

    private function refreshLines(): void
    {
        $clearance = app(MedicineFinancialClearanceService::class);
        $locationId = $this->form->stock_location_id;
        $this->form->lines = $this->prescription->items()
            ->whereNull('terminal_status')
            ->with(['invoiceItem', 'medicine.dispensingUnit'])
            ->get()
            ->map(function ($item) use ($clearance, $locationId): array {
                $financiallyAvailable = (float) $clearance->forItem($item)['remaining_paid_quantity'];
                $stock = (float) ($item->medicine?->currentStock($locationId) ?? 0);

                return [
                    'prescription_item_id' => $item->id,
                    'medicine_id' => $item->medicine_id,
                    'quantity' => min($financiallyAvailable, $stock),
                ];
            })->all();
    }
}
