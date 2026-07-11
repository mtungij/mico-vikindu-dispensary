<?php

namespace App\Livewire\Laboratory;

use App\Models\LaboratoryOrder;
use App\Models\LaboratoryResult;
use App\Models\LaboratorySample;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Dashboard extends Component
{
    public function mount(): void { Gate::authorize('laboratory.view-dashboard'); }
    public function render(): View
    {
        return view('livewire.laboratory.dashboard', [
            'cards' => [
                'Orders Today' => LaboratoryOrder::query()->forCurrentFacility()->whereDate('ordered_at', today())->count(),
                'Awaiting Sample' => LaboratoryOrder::query()->forCurrentFacility()->whereIn('status', ['ordered', 'awaiting_payment'])->count(),
                'Samples Collected' => LaboratorySample::query()->forCurrentFacility()->where('sample_status', 'collected')->count(),
                'Processing' => LaboratorySample::query()->forCurrentFacility()->where('sample_status', 'processing')->count(),
                'Results Pending' => LaboratoryResult::query()->forCurrentFacility()->whereIn('result_status', ['draft', 'entered'])->count(),
                'Awaiting Verification' => LaboratoryResult::query()->forCurrentFacility()->where('result_status', 'pending_verification')->count(),
                'Critical Results' => LaboratoryResult::query()->forCurrentFacility()->where('abnormal_flag', 'critical')->count(),
                'Completed Today' => LaboratoryOrder::query()->forCurrentFacility()->whereDate('completed_at', today())->count(),
                'Rejected Samples' => LaboratorySample::query()->forCurrentFacility()->whereIn('sample_status', ['rejected', 'recollection_required'])->count(),
            ],
            'urgent' => LaboratoryOrder::query()->forCurrentFacility()->with('patient')->where('priority', 'urgent')->latest()->limit(8)->get(),
            'verification' => LaboratoryResult::query()->forCurrentFacility()->with(['order.patient','test'])->where('result_status', 'pending_verification')->latest()->limit(8)->get(),
        ])->layout('components.layouts.app', ['title' => 'Laboratory Dashboard', 'description' => 'Muhtasari wa samples, results na verification.']);
    }
}
