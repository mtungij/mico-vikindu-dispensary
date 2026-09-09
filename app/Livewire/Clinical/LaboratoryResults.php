<?php

namespace App\Livewire\Clinical;

use App\Models\ActivityLog;
use App\Models\LaboratoryResult;
use App\Models\Visit;
use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class LaboratoryResults extends Component
{
    public function mount(): void
    {
        Gate::authorize('laboratory-results.view');
    }

    public function markReviewed(LaboratoryResult $result): void
    {
        Gate::authorize('view', $result);
        abort_unless($result->result_status->value === 'released', 404);

        DB::transaction(function () use ($result): void {
            Visit::query()->lockForUpdate()->findOrFail($result->order->visit_id);
            $result = LaboratoryResult::query()->lockForUpdate()->findOrFail($result->id);
            if ($result->reviewed_at) {
                return;
            }
            abort_unless($result->result_status->value === 'released', 409);
            $result->update([
                'reviewed_by_clinician' => auth()->id(),
                'reviewed_at' => now(),
            ]);
            ActivityLog::query()->create([
                'user_id' => auth()->id(),
                'event' => 'clinician_reviewed_result',
                'subject_type' => $result::class,
                'subject_id' => $result->id,
            ]);
        });

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
