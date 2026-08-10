<?php

namespace App\Livewire\Laboratory;

use App\Models\LaboratoryOrder;
use App\Models\LaboratoryResult;
use App\Models\LaboratorySample;
use App\Models\LaboratoryTest;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class Dashboard extends Component
{
    use WithPagination;

    public string $worklistTab = 'pending_verification';

    public string $search = '';

    public string $dateRange = 'all';

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public string $testFilter = '';

    public string $sourceFilter = '';

    public string $priorityFilter = '';

    public string $flagFilter = '';

    public function mount(): void
    {
        Gate::authorize('laboratory.view-dashboard');
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['worklistTab', 'search', 'dateRange', 'dateFrom', 'dateTo', 'testFilter', 'sourceFilter', 'priorityFilter', 'flagFilter'], true)) {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        $latestResults = $this->latestResultVersionsQuery();
        $results = $this->worklistQuery()
            ->with(['order.patient', 'order.visit', 'order.items.results', 'test', 'verifier', 'releaser'])
            ->paginate(20);

        $reportEligibility = $results->getCollection()
            ->filter(fn (LaboratoryResult $result): bool => $result->result_status->value === 'released')
            ->unique('laboratory_order_id')
            ->mapWithKeys(fn (LaboratoryResult $result): array => [
                $result->laboratory_order_id => $this->loadedOrderIsReportEligible($result->order),
            ]);

        return view('livewire.laboratory.dashboard', [
            'cards' => [
                'Orders Today' => LaboratoryOrder::query()->forCurrentFacility()->whereDate('ordered_at', today())->count(),
                'Awaiting Sample' => LaboratoryOrder::query()->forCurrentFacility()->whereIn('status', ['ordered', 'awaiting_payment'])->count(),
                'Samples Collected' => LaboratorySample::query()->forCurrentFacility()->where('sample_status', 'collected')->count(),
                'Processing' => LaboratorySample::query()->forCurrentFacility()->where('sample_status', 'processing')->count(),
                'Results Pending' => (clone $latestResults)->whereIn('result_status', ['draft', 'entered'])->count(),
                'Awaiting Verification' => (clone $latestResults)->where('result_status', 'pending_verification')->count(),
                'Awaiting Release' => (clone $latestResults)->where('result_status', 'verified')->count(),
                'Critical Results Today' => (clone $latestResults)->where('abnormal_flag', 'critical')->whereDate('entered_at', today())->count(),
                'Completed Today' => (clone $latestResults)->where('result_status', 'released')->whereDate('released_at', today())->count(),
                'Rejected Samples' => LaboratorySample::query()->forCurrentFacility()->whereIn('sample_status', ['rejected', 'recollection_required'])->count(),
            ],
            'urgent' => LaboratoryOrder::query()->forCurrentFacility()->with('patient')->whereIn('priority', ['stat', 'urgent'])->whereNotIn('status', ['completed', 'cancelled'])->oldest('ordered_at')->limit(8)->get(),
            'results' => $results,
            'reportEligibility' => $reportEligibility,
            'tests' => LaboratoryTest::query()->forCurrentFacility()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ])->layout('components.layouts.app', ['title' => 'Laboratory Dashboard', 'description' => 'Muhtasari wa samples, results na verification.']);
    }

    private function worklistQuery(): Builder
    {
        $query = $this->latestResultVersionsQuery()
            ->join('laboratory_orders as worklist_orders', 'worklist_orders.id', '=', 'laboratory_results.laboratory_order_id')
            ->whereNull('worklist_orders.deleted_at');

        $status = match ($this->worklistTab) {
            'verified' => 'verified',
            'released' => 'released',
            default => 'pending_verification',
        };
        $query->where('laboratory_results.result_status', $status);

        $query
            ->when($this->search !== '', function (Builder $query): void {
                $term = '%'.trim($this->search).'%';
                $query->where(fn (Builder $search) => $search
                    ->where('worklist_orders.order_number', 'like', $term)
                    ->orWhereHas('order.patient', fn (Builder $patient) => $patient
                        ->where('first_name', 'like', $term)
                        ->orWhere('middle_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhere('patient_number', 'like', $term))
                    ->orWhereHas('order.visit', fn (Builder $visit) => $visit->where('visit_number', 'like', $term))
                    ->orWhereHas('test', fn (Builder $test) => $test->where('name', 'like', $term)));
            })
            ->when($this->testFilter !== '', fn (Builder $query) => $query->where('laboratory_results.laboratory_test_id', $this->testFilter))
            ->when($this->sourceFilter !== '', fn (Builder $query) => $query->where('worklist_orders.source', $this->sourceFilter))
            ->when($this->priorityFilter !== '', fn (Builder $query) => $query->where('worklist_orders.priority', $this->priorityFilter))
            ->when($this->flagFilter !== '', fn (Builder $query) => $query->where('laboratory_results.abnormal_flag', $this->flagFilter));

        $dateColumn = $status === 'released' ? 'laboratory_results.released_at' : 'laboratory_results.entered_at';
        match ($this->dateRange) {
            'today' => $query->whereDate($dateColumn, today()),
            '7_days' => $query->whereDate($dateColumn, '>=', today()->subDays(6)),
            '30_days' => $query->whereDate($dateColumn, '>=', today()->subDays(29)),
            'custom' => $query
                ->when($this->dateFrom, fn (Builder $query) => $query->whereDate($dateColumn, '>=', $this->dateFrom))
                ->when($this->dateTo, fn (Builder $query) => $query->whereDate($dateColumn, '<=', $this->dateTo)),
            default => null,
        };

        return $status === 'released'
            ? $query->orderByDesc('laboratory_results.released_at')->orderByDesc('laboratory_results.id')
            : $query
                ->orderByRaw("CASE WHEN worklist_orders.priority IN ('stat', 'urgent') THEN 0 ELSE 1 END")
                ->orderByRaw("CASE WHEN laboratory_results.abnormal_flag = 'critical' THEN 0 ELSE 1 END")
                ->orderBy('laboratory_results.entered_at')
                ->orderBy('laboratory_results.id');
    }

    private function latestResultVersionsQuery(): Builder
    {
        return LaboratoryResult::query()
            ->select('laboratory_results.*')
            ->where('laboratory_results.facility_id', currentFacility()?->id)
            ->whereNotExists(fn ($newer) => $newer
                ->selectRaw('1')
                ->from('laboratory_results as newer_results')
                ->whereColumn('newer_results.laboratory_order_item_id', 'laboratory_results.laboratory_order_item_id')
                ->whereNull('newer_results.deleted_at')
                ->where(fn ($version) => $version
                    ->whereColumn('newer_results.result_version', '>', 'laboratory_results.result_version')
                    ->orWhere(fn ($sameVersion) => $sameVersion
                        ->whereColumn('newer_results.result_version', 'laboratory_results.result_version')
                        ->whereColumn('newer_results.id', '>', 'laboratory_results.id'))));
    }

    private function loadedOrderIsReportEligible(LaboratoryOrder $order): bool
    {
        $items = $order->items->whereNotIn('status', ['cancelled', 'entered_in_error']);

        return $items->isNotEmpty() && ! $items->contains(function ($item): bool {
            $result = $item->results->sortByDesc('result_version')->first();

            return $item->status !== 'completed'
                || $item->result_status !== 'released'
                || $result?->result_status?->value !== 'released'
                || ! $result->entered_by
                || ! $result->entered_at
                || ! $result->verified_by
                || ! $result->verified_at
                || ! $result->released_by
                || ! $result->released_at;
        });
    }
}
