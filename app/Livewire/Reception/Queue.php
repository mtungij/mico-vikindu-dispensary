<?php

namespace App\Livewire\Reception;

use App\Models\Department;
use App\Models\PatientQueue;
use App\Services\LaboratoryReportService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Queue extends Component
{
    public string $department = '';

    public string $status = '';

    public string $priority = '';

    public string $search = '';

    public function mount(): void
    {
        Gate::authorize('reception.manage-queue');
    }

    public function callPatient(PatientQueue $queue): void
    {
        $queue->update(['queue_status' => 'called', 'called_at' => now()]);
    }

    public function skip(PatientQueue $queue): void
    {
        $queue->update(['queue_status' => 'skipped', 'skipped_at' => now()]);
    }

    public function complete(PatientQueue $queue): void
    {
        $queue->update(['queue_status' => 'completed', 'service_completed_at' => now()]);
    }

    public function render(LaboratoryReportService $reports): View
    {
        $queues = PatientQueue::query()->forCurrentFacility()->with(['patient', 'visit.laboratoryOrders.items.results', 'department'])->whereDate('queue_date', today())
            ->when($this->department, fn ($q) => $q->where('department_id', $this->department))->when($this->status, fn ($q) => $q->where('queue_status', $this->status))->when($this->priority, fn ($q) => $q->where('priority', $this->priority))
            ->when($this->search, fn ($q) => $q->whereHas('patient', fn ($q) => $q->where('first_name', 'like', "%{$this->search}%")->orWhere('last_name', 'like', "%{$this->search}%")->orWhere('patient_number', 'like', "%{$this->search}%")))
            ->orderByRaw("case priority when 'emergency' then 1 when 'urgent' then 2 else 3 end")->orderBy('created_at')->get();
        $reportEligibility = $queues
            ->flatMap(fn (PatientQueue $queue) => $queue->visit->laboratoryOrders)
            ->mapWithKeys(fn ($order): array => [$order->id => $reports->isEligible($order)]);

        return view('livewire.reception.queue', [
            'queues' => $queues,
            'departments' => Department::query()->forCurrentFacility()->get(),
            'reportEligibility' => $reportEligibility,
        ])->layout('components.layouts.app', ['title' => 'Reception Queue', 'description' => 'Foleni ya wagonjwa.']);
    }
}
