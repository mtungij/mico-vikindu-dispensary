<?php

namespace App\Livewire\Dental;

use App\Models\DentalEncounter;
use App\Models\DentalLabOrder;
use App\Models\DentalProcedure;
use App\Models\DentalTreatmentPlan;
use App\Models\Visit;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Dashboard extends Component
{
    public function mount(): void { Gate::authorize('dental.view-dashboard'); }
    public function render(): View
    {
        $facilityId = currentFacility()?->id;
        return view('livewire.dental.dashboard', [
            'cards' => [
                'Dental Patients Today' => DentalEncounter::query()->where('facility_id',$facilityId)->whereDate('created_at', today())->count(),
                'Waiting' => Visit::query()->where('facility_id',$facilityId)->whereHas('destinationDepartment', fn($q)=>$q->where('code','DEN'))->whereIn('visit_status',['awaiting_department','in_queue'])->count(),
                'In Consultation' => DentalEncounter::query()->where('facility_id',$facilityId)->where('status','in_progress')->count(),
                'Procedures Completed' => DentalProcedure::query()->where('facility_id',$facilityId)->whereDate('completed_at', today())->count(),
                'Treatment Plans Pending' => DentalTreatmentPlan::query()->where('facility_id',$facilityId)->whereIn('status',['draft','proposed'])->count(),
                'Dental Lab Orders Pending' => DentalLabOrder::query()->where('facility_id',$facilityId)->whereIn('status',['draft','prepared','sent','in_progress'])->count(),
            ],
            'recentProcedures' => DentalProcedure::query()->where('facility_id',$facilityId)->with(['patient','performer'])->latest()->limit(8)->get(),
        ])->layout('components.layouts.app', ['title'=>'Dental Dashboard','description'=>'Muhtasari wa huduma za meno.']);
    }
}
