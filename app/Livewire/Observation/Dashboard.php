<?php

namespace App\Livewire\Observation;

use App\Models\Bed;
use App\Models\BedCleaningRecord;
use App\Models\ClinicalAlert;
use App\Models\IvFluidAdministration;
use App\Models\MedicationAdministration;
use App\Models\NursingTask;
use App\Models\ObservationAdmission;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Dashboard extends Component
{
    public function mount(): void { Gate::authorize('observation.view-dashboard'); }
    public function render(): View
    {
        $stats = [
            ['label'=>'Total Beds','value'=>Bed::query()->forCurrentFacility()->count(),'icon'=>'bed','tone'=>'blue'],
            ['label'=>'Available Beds','value'=>Bed::query()->forCurrentFacility()->where('status','available')->count(),'icon'=>'bed-single','tone'=>'green'],
            ['label'=>'Occupied Beds','value'=>Bed::query()->forCurrentFacility()->where('status','occupied')->count(),'icon'=>'bed','tone'=>'amber'],
            ['label'=>'Cleaning','value'=>Bed::query()->forCurrentFacility()->where('status','cleaning')->count(),'icon'=>'spray-can','tone'=>'indigo'],
            ['label'=>'Under Observation','value'=>ObservationAdmission::query()->forCurrentFacility()->whereIn('status',['admitted','under_observation'])->count(),'icon'=>'heart-pulse','tone'=>'teal'],
            ['label'=>'Awaiting Bed','value'=>ObservationAdmission::query()->forCurrentFacility()->where('status','awaiting_bed')->count(),'icon'=>'clock','tone'=>'amber'],
            ['label'=>'Medication Due','value'=>MedicationAdministration::query()->forCurrentFacility()->whereIn('administration_status',['scheduled','due','late'])->where('scheduled_at','<=',now())->count(),'icon'=>'pill','tone'=>'red'],
            ['label'=>'Overdue Tasks','value'=>NursingTask::query()->forCurrentFacility()->whereNotNull('due_at')->where('due_at','<',now())->whereNotIn('status',['completed','cancelled'])->count(),'icon'=>'clipboard-list','tone'=>'red'],
            ['label'=>'IV Running','value'=>IvFluidAdministration::query()->forCurrentFacility()->where('status','running')->count(),'icon'=>'droplets','tone'=>'blue'],
            ['label'=>'Ready Discharge','value'=>ObservationAdmission::query()->forCurrentFacility()->where('status','ready_for_discharge')->count(),'icon'=>'log-out','tone'=>'green'],
            ['label'=>'Critical Alerts','value'=>ClinicalAlert::query()->forCurrentFacility()->where('severity','critical')->whereIn('status',['active','acknowledged'])->count(),'icon'=>'triangle-alert','tone'=>'red'],
        ];
        return view('livewire.observation.dashboard', ['stats'=>$stats, 'admissions'=>ObservationAdmission::query()->forCurrentFacility()->with(['patient','bed','room'])->latest('admitted_at')->limit(8)->get(), 'cleaning'=>BedCleaningRecord::query()->forCurrentFacility()->with('bed')->latest('requested_at')->limit(8)->get()])
            ->layout('components.layouts.app', ['title'=>'Bed Rest Dashboard','description'=>'Muhtasari wa vitanda, admissions, tasks na medication.']);
    }
}
