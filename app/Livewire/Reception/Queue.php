<?php

namespace App\Livewire\Reception;

use App\Models\Department;
use App\Models\PatientQueue;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Queue extends Component
{
    public string $department = ''; public string $status = ''; public string $priority = ''; public string $search = '';
    public function mount(): void { Gate::authorize('reception.manage-queue'); }
    public function callPatient(PatientQueue $queue): void { $queue->update(['queue_status' => 'called', 'called_at' => now()]); }
    public function skip(PatientQueue $queue): void { $queue->update(['queue_status' => 'skipped', 'skipped_at' => now()]); }
    public function complete(PatientQueue $queue): void { $queue->update(['queue_status' => 'completed', 'service_completed_at' => now()]); }
    public function render(): View
    {
        $queues = PatientQueue::query()->forCurrentFacility()->with(['patient','visit','department'])->whereDate('queue_date', today())
            ->when($this->department, fn($q) => $q->where('department_id', $this->department))->when($this->status, fn($q) => $q->where('queue_status', $this->status))->when($this->priority, fn($q) => $q->where('priority', $this->priority))
            ->when($this->search, fn($q) => $q->whereHas('patient', fn($q) => $q->where('first_name','like',"%{$this->search}%")->orWhere('last_name','like',"%{$this->search}%")->orWhere('patient_number','like',"%{$this->search}%")))
            ->orderByRaw("case priority when 'emergency' then 1 when 'urgent' then 2 else 3 end")->orderBy('created_at')->get();
        return view('livewire.reception.queue', ['queues' => $queues, 'departments' => Department::query()->forCurrentFacility()->get()])->layout('components.layouts.app', ['title' => 'Reception Queue', 'description' => 'Foleni ya wagonjwa.']);
    }
}
