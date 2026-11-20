<?php

namespace App\Livewire\Clinical;

use App\Models\LaboratoryResult;
use App\Services\LaboratoryResultReleaseService;
use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class LaboratoryResults extends Component
{
    public function mount(): void
    {
        Gate::authorize('laboratory-results.view');
    }

    public function markReviewed(LaboratoryResult $result, LaboratoryResultReleaseService $release): void
    {
        $release->markReviewed($result, auth()->user());

        Notifier::success('laboratory_results.reviewed');
    }

    public function render(): View
    {
        return view('livewire.clinical.laboratory-results', [
            'results' => LaboratoryResult::query()
                ->forCurrentFacility()
                ->with(['order.patient', 'test'])
                ->where('result_status', 'released')
                ->latest('released_at')
                ->paginate(15),
        ])->layout('components.layouts.app', [
            'title' => 'Laboratory Results',
            'description' => 'Matokeo yaliyotolewa kwa clinician review.',
        ]);
    }
}
