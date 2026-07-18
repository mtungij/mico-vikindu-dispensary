<?php

namespace App\Livewire\Rch;

use App\Enums\VisitStatus;
use App\Models\Department;
use App\Models\PatientQueue;
use App\Models\User;
use App\Models\Visit;
use App\Services\RchWorkflowService;
use App\Services\WorkflowService;
use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class Queue extends Component
{
    use WithPagination;

    public string $search = ''; public string $status = ''; public string $payer = ''; public string $priority = ''; public ?int $provider = null;
    public function mount(): void { Gate::authorize('rch.view-queue'); }
    public function call(int $queueId, WorkflowService $workflow): void { Gate::authorize('rch.view-queue'); $workflow->callQueue(PatientQueue::query()->forCurrentFacility()->findOrFail($queueId), auth()->user()); Notifier::success('Patient called.'); }
    public function start(int $visitId, RchWorkflowService $workflow): mixed { Gate::authorize('rch.start-encounter'); $encounter = $workflow->startEncounter(Visit::query()->forCurrentFacility()->findOrFail($visitId), 'rch_general', auth()->user()); Notifier::success('RCH encounter imeanza.'); return redirect()->route('rch.encounter', $encounter->visit_id); }
    public function skip(int $queueId, WorkflowService $workflow): void { $workflow->skipQueue(PatientQueue::query()->forCurrentFacility()->findOrFail($queueId), auth()->user(), 'Skipped from RCH queue'); }
    public function cancel(int $queueId, WorkflowService $workflow): void { $workflow->cancelQueue(PatientQueue::query()->forCurrentFacility()->findOrFail($queueId), auth()->user(), 'Cancelled from RCH queue'); }
    public function assignProvider(int $visitId): void { Gate::authorize('rch.assign-provider'); Visit::query()->forCurrentFacility()->findOrFail($visitId)->update(['current_assigned_user_id' => $this->provider, 'updated_by' => auth()->id()]); }

    public function render(): View
    {
        $visits = Visit::query()->forCurrentFacility()->with(['patient','destinationDepartment','currentQueue','invoice','currentAssignedUser'])
            ->whereHas('destinationDepartment', fn($q) => $q->where('code', 'RCH'))
            ->when($this->status, fn($q) => $q->where('visit_status', $this->status))
            ->when(! $this->status, fn($q) => $q->whereIn('visit_status', [VisitStatus::AwaitingPayment->value, VisitStatus::AwaitingDepartment->value, VisitStatus::InQueue->value, VisitStatus::InProgress->value, VisitStatus::Waiting->value, VisitStatus::InConsultation->value, VisitStatus::AwaitingLab->value, VisitStatus::AwaitingPharmacy->value, VisitStatus::Completed->value, VisitStatus::Referred->value]))
            ->when($this->payer, fn($q) => $q->where('payer_type', $this->payer))
            ->when($this->priority, fn($q) => $q->where('priority', $this->priority))
            ->when($this->search, fn($q) => $q->whereHas('patient', fn($p) => $p->where('first_name','like',"%{$this->search}%")->orWhere('last_name','like',"%{$this->search}%")->orWhere('patient_number','like',"%{$this->search}%")))
            ->latest('registered_at')->paginate(12);
        return view('livewire.rch.queue', ['visits' => $visits, 'providers' => User::query()->orderBy('name')->limit(100)->get(), 'rchDepartment' => Department::query()->where('facility_id', currentFacility()?->id)->where('code','RCH')->first()])
            ->layout('components.layouts.app', ['title' => 'RCH Queue', 'description' => 'RCH patient flow, payments, encounters and handoffs.']);
    }
}
