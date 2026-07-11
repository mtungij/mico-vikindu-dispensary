<?php

namespace App\Livewire\Opd;

use App\Enums\VisitStatus;
use App\Models\PatientQueue;
use App\Models\Visit;
use App\Services\ClinicalEncounterService;
use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class Queue extends Component
{
    use WithPagination;
    public string $search = '';
    public string $priority = '';
    public string $payerType = '';

    public function mount(): void { Gate::authorize('opd.view-queue'); }

    public function startConsultation(Visit $visit, ClinicalEncounterService $service): mixed
    {
        Gate::authorize('opd.start-consultation');
        abort_unless($visit->facility_id === currentFacility()?->id, 404);
        $service->startEncounter($visit, auth()->user());
        Notifier::success('Consultation imeanza.');
        return redirect()->route('opd.consultation', $visit);
    }

    public function render(): View
    {
        $visits = Visit::query()->forCurrentFacility()->with(['patient', 'latestTriageAssessment', 'activeClinicalEncounter', 'invoice'])
            ->whereIn('visit_status', [VisitStatus::AwaitingDepartment->value, VisitStatus::InQueue->value, VisitStatus::InConsultation->value])
            ->when($this->priority, fn ($q) => $q->where('priority', $this->priority))
            ->when($this->payerType, fn ($q) => $q->where('payer_type', $this->payerType))
            ->when($this->search, fn ($q) => $q->whereHas('patient', fn ($p) => $p->where('first_name', 'like', "%{$this->search}%")->orWhere('last_name', 'like', "%{$this->search}%")->orWhere('patient_number', 'like', "%{$this->search}%")))
            ->orderByRaw("case priority when 'emergency' then 1 when 'urgent' then 2 else 3 end")
            ->oldest('registered_at')
            ->paginate(10);

        return view('livewire.opd.queue', [
            'visits' => $visits,
            'queues' => PatientQueue::query()->forCurrentFacility()->whereDate('queue_date', today())->get()->keyBy('visit_id'),
        ])->layout('components.layouts.app', ['title' => 'Foleni ya OPD', 'description' => 'Wagonjwa wanaosubiri consultation.']);
    }
}
