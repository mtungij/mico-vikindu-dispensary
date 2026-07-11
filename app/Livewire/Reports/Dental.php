<?php

namespace App\Livewire\Reports;

use App\Models\DentalDiagnosis;
use App\Models\DentalEncounter;
use App\Models\DentalProcedure;
use App\Models\DentalTreatmentPlan;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Dental extends Component
{
    public string $type = 'patients'; public ?string $dateFrom = null; public ?string $dateTo = null;
    public function mount(string $type = 'patients'): void { Gate::authorize('dental.reports.view'); $this->type=$type; }
    public function render(): View
    {
        $facilityId=currentFacility()?->id;
        $rows = match ($this->type) {
            'diagnoses' => DentalDiagnosis::query()->where('facility_id',$facilityId)->with('patient')->latest()->limit(100)->get(),
            'procedures','materials','revenue','provider-workload' => DentalProcedure::query()->where('facility_id',$facilityId)->with(['patient','service','performer'])->latest()->limit(100)->get(),
            'treatment-plans' => DentalTreatmentPlan::query()->where('facility_id',$facilityId)->with('patient')->latest()->limit(100)->get(),
            default => DentalEncounter::query()->where('facility_id',$facilityId)->with(['patient','provider'])->latest()->limit(100)->get(),
        };
        return view('livewire.reports.dental', ['rows'=>$rows])->layout('components.layouts.app',['title'=>'Dental Reports','description'=>'Ripoti za dental: '.$this->type]);
    }
}
