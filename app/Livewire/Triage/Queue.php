<?php

namespace App\Livewire\Triage;

use App\Enums\VisitStatus;
use App\Models\Department;
use App\Models\PatientQueue;
use App\Models\Visit;
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
    public string $visitType = '';
    public string $department = '';
    public string $status = '';

    public function mount(): void { Gate::authorize('triage.access'); }

    public function markEmergency(Visit $visit): void
    {
        Gate::authorize('triage.set-emergency-priority');
        $this->authorizeVisit($visit);
        $visit->update(['priority' => 'emergency', 'updated_by' => auth()->id()]);
        Notifier::success('Emergency priority imewekwa.');
    }

    public function render(): View
    {
        $visits = Visit::query()->forCurrentFacility()->with(['patient.primaryPayerProfile', 'destinationDepartment', 'invoice'])
            ->whereIn('visit_status', [VisitStatus::AwaitingTriage->value, VisitStatus::InQueue->value])
            ->when($this->priority, fn ($q) => $q->where('priority', $this->priority))
            ->when($this->payerType, fn ($q) => $q->where('payer_type', $this->payerType))
            ->when($this->visitType, fn ($q) => $q->where('visit_type', $this->visitType))
            ->when($this->department, fn ($q) => $q->where('destination_department_id', $this->department))
            ->when($this->search, fn ($q) => $q->whereHas('patient', fn ($p) => $p->where('first_name', 'like', "%{$this->search}%")->orWhere('last_name', 'like', "%{$this->search}%")->orWhere('patient_number', 'like', "%{$this->search}%")))
            ->orderByRaw("case priority when 'emergency' then 1 when 'urgent' then 2 else 3 end")
            ->oldest('registered_at')
            ->paginate(10);

        $queues = PatientQueue::query()->forCurrentFacility()->whereDate('queue_date', today())->get()->keyBy('visit_id');

        return view('livewire.triage.queue', [
            'visits' => $visits,
            'queues' => $queues,
            'departments' => Department::query()->forCurrentFacility()->where('is_active', true)->get(),
        ])->layout('components.layouts.app', ['title' => 'Foleni ya Triage', 'description' => 'Wagonjwa wanaosubiri vipimo muhimu na priority ya kliniki.']);
    }

    private function authorizeVisit(Visit $visit): void
    {
        abort_unless($visit->facility_id === currentFacility()?->id, 404);
    }
}
