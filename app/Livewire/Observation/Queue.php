<?php

namespace App\Livewire\Observation;

use App\Models\ObservationAdmission;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class Queue extends Component
{
    use WithPagination;
    public string $tab = 'admitted'; public string $search = '';
    public function mount(): void { Gate::authorize('observation.access'); }
    public function render(): View
    {
        $rows = ObservationAdmission::query()->forCurrentFacility()->with(['patient','bed','room'])
            ->when($this->search, fn($q)=>$q->where('admission_number','like',"%{$this->search}%")->orWhereHas('patient', fn($p)=>$p->where('first_name','like',"%{$this->search}%")->orWhere('last_name','like',"%{$this->search}%")->orWhere('patient_number','like',"%{$this->search}%")))
            ->when($this->tab !== 'all', fn($q)=>$q->where('status', match($this->tab){'awaiting_payment'=>'awaiting_payment','awaiting_bed'=>'awaiting_bed','ready'=>'ready_for_discharge','referred'=>'referred','discharged_today'=>'discharged', default=>'under_observation'}))
            ->when($this->tab === 'discharged_today', fn($q)=>$q->whereDate('actual_discharge_at', today()))
            ->latest('admitted_at')->paginate(12);
        return view('livewire.observation.queue', ['admissions'=>$rows])->layout('components.layouts.app', ['title'=>'Observation Queue','description'=>'Foleni ya wagonjwa wa Bed Rest / Uangalizi.']);
    }
}
