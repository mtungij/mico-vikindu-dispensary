<?php

namespace App\Livewire\Reports;

use App\Models\PatientReferral;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Referrals extends Component
{
    public function mount(): void { Gate::authorize('reports.view'); }
    public function render(): View
    {
        return view('livewire.reports.referrals', ['rows' => PatientReferral::query()->forCurrentFacility()->with('patient')->latest('referred_at')->limit(50)->get()])
            ->layout('components.layouts.app', ['title' => 'Referral Report', 'description' => 'Ripoti ya rufaa.']);
    }
}
