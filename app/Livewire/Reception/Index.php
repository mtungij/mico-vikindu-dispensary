<?php

namespace App\Livewire\Reception;

use App\Models\Patient;
use App\Models\PatientQueue;
use App\Models\Visit;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Index extends Component
{
    public function mount(): void { Gate::authorize('reception.access'); }
    public function render(): View
    {
        $today = today();
        return view('livewire.reception.index', [
            'patientsToday' => Patient::query()->forCurrentFacility()->whereDate('registered_at', $today)->count(),
            'newPatientsToday' => Visit::query()->forCurrentFacility()->whereDate('registered_at', $today)->where('visit_type', 'new_patient')->count(),
            'returningToday' => Visit::query()->forCurrentFacility()->whereDate('registered_at', $today)->where('visit_type', 'returning_patient')->count(),
            'awaitingPayment' => Visit::query()->forCurrentFacility()->where('visit_status', 'awaiting_payment')->count(),
            'awaitingTriage' => Visit::query()->forCurrentFacility()->where('visit_status', 'awaiting_triage')->count(),
            'activeVisits' => Visit::query()->forCurrentFacility()->whereNotIn('visit_status', ['completed','cancelled','discharged'])->count(),
            'queues' => PatientQueue::query()->forCurrentFacility()->with(['patient','visit','department'])->whereDate('queue_date', $today)->latest()->limit(20)->get(),
        ])->layout('components.layouts.app', ['title' => 'Reception', 'description' => 'Dashboard ya usajili, visits na foleni.']);
    }
}
