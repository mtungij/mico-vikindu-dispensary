<?php

namespace App\Livewire\Dental;

use App\Enums\VisitStatus;
use App\Models\PatientQueue;
use App\Models\Visit;
use App\Services\DentalEncounterService;
use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class Queue extends Component
{
    use WithPagination;
    public string $search = ''; public string $status = ''; public string $payerType = ''; public string $priority = ''; public ?string $overrideReason = null;
    public function mount(): void { Gate::authorize('dental.view-queue'); }
    public function startConsultation(Visit $visit, DentalEncounterService $service): mixed
    {
        Gate::authorize('dental.start-consultation');
        $service->start($visit, auth()->user(), $this->overrideReason);
        Notifier::success('Dental consultation imeanza.');
        return redirect()->route('dental.consultation', $visit);
    }
    public function render(): View
    {
        $visits = Visit::query()->forCurrentFacility()->with(['patient','destinationDepartment','activeClinicalEncounter','invoice.items','payerProfile'])
            ->whereHas('destinationDepartment', fn($q)=>$q->where('code','DEN'))
            ->when($this->status, fn($q)=>$q->where('visit_status',$this->status))
            ->when(! $this->status, fn($q)=>$q->whereIn('visit_status',[VisitStatus::AwaitingPayment->value,VisitStatus::AwaitingDepartment->value,VisitStatus::InQueue->value,VisitStatus::InProgress->value,VisitStatus::InConsultation->value,VisitStatus::Completed->value,VisitStatus::Cancelled->value]))
            ->when($this->payerType, fn($q)=>$q->where('payer_type',$this->payerType))
            ->when($this->priority, fn($q)=>$q->where('priority',$this->priority))
            ->when($this->search, fn($q)=>$q->whereHas('patient', fn($p)=>$p->where('first_name','like',"%{$this->search}%")->orWhere('last_name','like',"%{$this->search}%")->orWhere('patient_number','like',"%{$this->search}%")))
            ->latest('registered_at')->paginate(12);
        return view('livewire.dental.queue', ['visits'=>$visits,'queues'=>PatientQueue::query()->forCurrentFacility()->whereDate('queue_date', today())->get()->keyBy('visit_id')])
            ->layout('components.layouts.app', ['title'=>'Foleni ya Dental','description'=>'Wagonjwa wa meno, malipo na consultations.']);
    }
}
