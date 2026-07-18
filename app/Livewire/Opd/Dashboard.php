<?php

namespace App\Livewire\Opd;

use App\Enums\VisitStatus;
use App\Models\ClinicalAlert;
use App\Models\ClinicalEncounter;
use App\Models\Visit;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Dashboard extends Component
{
    public function mount(): void { Gate::authorize('opd.access'); }
    public function render(): View
    {
        $facilityId = currentFacility()?->id;
        return view('livewire.opd.dashboard', [
            'cards' => [
                'Waiting patients' => Visit::query()->where('facility_id', $facilityId)->whereIn('visit_status', [VisitStatus::AwaitingDepartment, VisitStatus::InQueue, VisitStatus::InProgress])->count(),
                'In consultation' => Visit::query()->where('facility_id', $facilityId)->where('visit_status', VisitStatus::InConsultation)->count(),
                'Awaiting lab' => Visit::query()->where('facility_id', $facilityId)->where('visit_status', VisitStatus::AwaitingLab)->count(),
                'Awaiting pharmacy' => Visit::query()->where('facility_id', $facilityId)->where('visit_status', VisitStatus::AwaitingPharmacy)->count(),
                'Completed today' => ClinicalEncounter::query()->where('facility_id', $facilityId)->whereDate('completed_at', today())->count(),
                'Critical alerts' => ClinicalAlert::query()->where('facility_id', $facilityId)->where('severity', 'critical')->whereIn('status', ['active', 'acknowledged'])->count(),
            ],
            'alerts' => ClinicalAlert::query()->forCurrentFacility()->with('patient')->whereIn('status', ['active', 'acknowledged'])->latest()->limit(8)->get(),
            'active' => ClinicalEncounter::query()->forCurrentFacility()->with('patient')->where('provider_user_id', auth()->id())->where('status', 'in_progress')->latest()->limit(8)->get(),
        ])->layout('components.layouts.app', ['title' => 'OPD Dashboard', 'description' => 'Muhtasari wa consultation, alerts na foleni.']);
    }
}
