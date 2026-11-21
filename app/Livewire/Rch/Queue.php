<?php

namespace App\Livewire\Rch;

use App\Enums\QueueStatus;
use App\Enums\RchService;
use App\Models\PatientQueue;
use App\Models\User;
use App\Models\Visit;
use App\Services\WorkflowService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Queue extends Component
{
    use WithPagination;

    #[Url]
    public string $programme = '';

    public string $search = '';

    public string $status = '';

    public string $payer = '';

    public string $priority = '';

    public ?int $provider = null;

    public function mount(): void
    {
        Gate::authorize('rch.view-queue');
    }

    public function updatedProgramme(): void
    {
        $this->resetPage();
    }

    public function call(int $queueId, WorkflowService $workflow): void
    {
        $workflow->callQueue($this->rchQueue($queueId), auth()->user());
    }

    public function start(int $visitId): mixed
    {
        Gate::authorize('rch.start-encounter');

        return redirect()->route('rch.encounter', $this->triagedVisits()->findOrFail($visitId));
    }

    public function skip(int $queueId, WorkflowService $workflow): void
    {
        $workflow->skipQueue($this->rchQueue($queueId), auth()->user(), 'Skipped from RCH queue');
    }

    public function cancel(int $queueId, WorkflowService $workflow): void
    {
        $workflow->cancelQueue($this->rchQueue($queueId), auth()->user(), 'Cancelled from RCH queue');
    }

    public function assignProvider(int $visitId): void
    {
        Gate::authorize('rch.assign-provider');
        abort_unless(auth()->user()->belongsToCurrentFacility(), 403);
        $provider = $this->providers()->findOrFail($this->provider);
        $this->triagedVisits()->whereNull('completed_at')->findOrFail($visitId)
            ->update(['current_assigned_user_id' => $provider->id, 'updated_by' => auth()->id()]);
    }

    private function rchQueue(int $queueId): PatientQueue
    {
        Gate::authorize('workflow.manage-rch-queue');
        abort_unless(auth()->user()->belongsToCurrentFacility(), 403);

        return PatientQueue::query()->forCurrentFacility()
            ->whereHas('department', fn ($query) => $query->where('facility_id', currentFacility()?->id)->where('code', 'RCH'))
            ->whereHas('visit', fn ($query) => $query
                ->whereIn('visits.id', $this->triagedVisits()->select('visits.id'))
                ->whereIn('visit_status', ['in_queue', 'in_progress', 'waiting', 'called', 'serving', 'awaiting_department'])
                ->whereDoesntHave('clinicalEncounters', fn ($clinical) => $clinical->where('encounter_type', 'rch')))
            ->whereIn('queue_status', [QueueStatus::Waiting, QueueStatus::Called, QueueStatus::Serving])
            ->findOrFail($queueId);
    }

    private function triagedVisits(): Builder
    {
        return Visit::query()->forCurrentFacility()
            ->whereHas('destinationDepartment', fn ($query) => $query->where('code', 'RCH')->where('facility_id', currentFacility()?->id))
            ->whereHas('triageAssessments', fn ($query) => $query->where('status', 'completed')->where('facility_id', currentFacility()?->id))
            ->whereNotIn('visit_status', ['cancelled', 'discharged', 'referred']);
    }

    private function providers(): Builder
    {
        return User::query()->whereHas('staffProfile', fn ($query) => $query->where('facility_id', currentFacility()?->id));
    }

    public function render(): View
    {
        Gate::authorize('rch.view-queue');
        abort_unless(auth()->user()->belongsToCurrentFacility(), 403);
        $programme = RchService::tryFrom($this->programme);
        $visits = $this->triagedVisits()->with(['patient', 'consultationService', 'currentQueue.department', 'invoice', 'currentAssignedUser'])
            ->when($programme, fn ($query) => $query->whereHas('consultationService', fn ($service) => $service
                ->where('facility_id', currentFacility()?->id)->where('code', $programme->value)))
            ->when($this->status, fn ($query) => $query->where('visit_status', $this->status))
            ->when($this->payer, fn ($query) => $query->where('payer_type', $this->payer))
            ->when($this->priority, fn ($query) => $query->where('priority', $this->priority))
            ->when($this->search, fn ($query) => $query->whereHas('patient', fn ($patient) => $patient
                ->where('first_name', 'like', "%{$this->search}%")->orWhere('last_name', 'like', "%{$this->search}%")
                ->orWhere('patient_number', 'like', "%{$this->search}%")))
            ->latest('registered_at')->paginate(12);

        return view('livewire.rch.queue', [
            'visits' => $visits,
            'providers' => auth()->user()->can('rch.assign-provider') ? $this->providers()->orderBy('name')->get() : collect(),
        ])->layout('components.layouts.app', ['title' => 'RCH Queue', 'description' => 'Patients who have completed Triage.']);
    }
}
