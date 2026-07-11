<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Models\Dispensing;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class DispensingLabelController extends Controller
{
    public function __invoke(Dispensing $dispensing): View
    {
        Gate::authorize('pharmacy.print-labels');
        abort_unless($dispensing->facility_id === currentFacility()?->id, 404);

        return view('pharmacy.dispensing-labels', [
            'dispensing' => $dispensing->load(['patient', 'items.medicine', 'items.prescriptionItem']),
        ]);
    }
}
