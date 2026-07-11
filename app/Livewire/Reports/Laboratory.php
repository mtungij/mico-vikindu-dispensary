<?php

namespace App\Livewire\Reports;

use App\Models\LaboratoryCriticalResultNotification;
use App\Models\LaboratoryOrder;
use App\Models\LaboratoryResult;
use App\Models\LaboratorySample;
use App\Models\LaboratoryTest;
use App\Services\LaboratoryTurnaroundTimeService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Laboratory extends Component
{
    public string $type = 'orders';

    public function mount(string $type = 'orders'): void
    {
        Gate::authorize('laboratory-reports.view');
        abort_unless(in_array($type, ['orders', 'tests', 'samples', 'results', 'critical-results', 'revenue', 'turnaround-time'], true), 404);
        $this->type = $type;
    }

    public function render(LaboratoryTurnaroundTimeService $tat): View
    {
        return view('livewire.reports.laboratory', [
            'rows' => $this->rows(),
            'summary' => $tat->summary(currentFacility()),
        ])->layout('components.layouts.app', [
            'title' => 'Laboratory Report',
            'description' => 'Ripoti za maabara, workload, revenue na turnaround time.',
        ]);
    }

    private function rows()
    {
        return match ($this->type) {
            'tests' => LaboratoryTest::query()->forCurrentFacility()->with(['category', 'specimenType'])->orderBy('name')->limit(50)->get(),
            'samples' => LaboratorySample::query()->forCurrentFacility()->with(['patient', 'specimenType'])->latest('collected_at')->limit(50)->get(),
            'results' => LaboratoryResult::query()->forCurrentFacility()->with(['order.patient', 'test'])->latest('entered_at')->limit(50)->get(),
            'critical-results' => LaboratoryCriticalResultNotification::query()->forCurrentFacility()->with(['result.order.patient', 'result.test'])->latest('notified_at')->limit(50)->get(),
            default => LaboratoryOrder::query()->forCurrentFacility()->with(['patient', 'items'])->latest('ordered_at')->limit(50)->get(),
        };
    }
}
