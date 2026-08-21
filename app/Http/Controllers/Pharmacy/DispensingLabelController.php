<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Models\Dispensing;
use App\Models\DispensingItem;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class DispensingLabelController extends Controller
{
    public function show(Dispensing $dispensing): View
    {
        $this->authorizeDispensing($dispensing);

        return view('pharmacy.dispensing-labels', [
            'dispensing' => $this->loadLabelData($dispensing),
        ]);
    }

    public function printAll(Dispensing $dispensing): View
    {
        $this->authorizeDispensing($dispensing);
        $dispensing = $this->loadLabelData($dispensing);

        return view('pharmacy.dispensing-label-print', ['dispensing' => $dispensing, 'items' => $dispensing->items]);
    }

    public function printItem(Dispensing $dispensing, DispensingItem $dispensingItem): View
    {
        $this->authorizeDispensing($dispensing);
        abort_unless($dispensingItem->dispensing_id === $dispensing->id, 404);

        $dispensing = $this->loadLabelData($dispensing);
        $dispensingItem->loadMissing(['medicine.dispensingUnit', 'prescriptionItem']);

        return view('pharmacy.dispensing-label-print', ['dispensing' => $dispensing, 'items' => collect([$dispensingItem])]);
    }

    private function authorizeDispensing(Dispensing $dispensing): void
    {
        Gate::authorize('pharmacy.print-labels');
        abort_unless($dispensing->facility_id === currentFacility()?->id, 404);
    }

    private function loadLabelData(Dispensing $dispensing): Dispensing
    {
        return $dispensing->load(['patient', 'location', 'dispenser.staffProfile', 'items.medicine.dispensingUnit', 'items.prescriptionItem']);
    }
}
