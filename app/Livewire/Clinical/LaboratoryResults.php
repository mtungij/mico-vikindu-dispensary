<?php

namespace App\Livewire\Clinical;

use App\Models\ActivityLog;
use App\Models\LaboratoryResult;
use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class LaboratoryResults extends Component
{
    public function mount(): void { Gate::authorize('opd.view-clinical-history'); }
    public function markReviewed(LaboratoryResult $result): void { abort_unless($result->facility_id === currentFacility()?->id && $result->result_status->value === 'released', 404); $result->update(['reviewed_by_clinician' => auth()->id(), 'reviewed_at' => now()]); ActivityLog::query()->create(['user_id' => auth()->id(), 'event' => 'clinician_reviewed_result', 'subject_type' => $result::class, 'subject_id' => $result->id]); Notifier::success('laboratory_results.reviewed'); }
    public function render(): View { return view('livewire.clinical.laboratory-results', ['results' => LaboratoryResult::query()->forCurrentFacility()->with(['order.patient','test'])->where('result_status', 'released')->latest('released_at')->paginate(15)])->layout('components.layouts.app', ['title' => 'Laboratory Results', 'description' => 'Matokeo yaliyotolewa kwa clinician review.']); }
}
