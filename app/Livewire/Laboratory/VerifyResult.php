<?php

namespace App\Livewire\Laboratory;

use App\Models\LaboratoryResult;
use App\Services\LaboratoryReportService;
use App\Services\LaboratoryResultReleaseService;
use App\Services\LaboratoryResultVerificationService;
use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class VerifyResult extends Component
{
    public LaboratoryResult $laboratoryResult;

    public string $returnReason = '';

    public function mount(LaboratoryResult $laboratoryResult): void
    {
        if ($laboratoryResult->result_status->value === 'verified') {
            Gate::authorize('release', $laboratoryResult);
        } else {
            Gate::authorize('verify', $laboratoryResult);
        }

        $this->laboratoryResult = $laboratoryResult;
        $this->reloadResult();
    }

    public function verify(
        LaboratoryResultVerificationService $service,
        LaboratoryResultReleaseService $release,
    ): void {
        Gate::authorize('verify', $this->laboratoryResult);
        $this->laboratoryResult = $service->verify($this->laboratoryResult, auth()->user());

        if (config('facility.laboratory_auto_release_after_verification', false)) {
            Gate::authorize('release', $this->laboratoryResult);
            $this->laboratoryResult = $release->release($this->laboratoryResult, auth()->user());
        }

        $this->reloadResult();
        $this->dispatch('laboratory-result-updated', orderId: $this->laboratoryResult->laboratory_order_id);
        Notifier::success('laboratory_results.verified');
    }

    public function release(LaboratoryResultReleaseService $service): void
    {
        $verifiedResults = $this->laboratoryResult->order->items()
            ->whereNotIn('status', ['cancelled', 'entered_in_error'])
            ->with(['results' => fn ($query) => $query->latest('result_version')])
            ->get()
            ->map(fn ($item) => $item->results->first())
            ->filter(fn (?LaboratoryResult $result): bool => $result?->result_status?->value === 'verified')
            ->values();

        if ($verifiedResults->isEmpty()) {
            throw ValidationException::withMessages([
                'result' => 'Hakuna matokeo yaliyohakikiwa yanayoweza kutolewa.',
            ]);
        }

        foreach ($verifiedResults as $result) {
            Gate::authorize('release', $result);
        }

        DB::transaction(function () use ($verifiedResults, $service): void {
            foreach ($verifiedResults as $result) {
                $service->release($result, auth()->user());
            }
        });

        $this->reloadResult();
        $this->dispatch('laboratory-result-updated', orderId: $this->laboratoryResult->laboratory_order_id);
        Notifier::success('laboratory_results.released');
    }

    public function returnForCorrection(LaboratoryResultVerificationService $service): void
    {
        Gate::authorize('verify', $this->laboratoryResult);
        $this->laboratoryResult = $service->returnForCorrection(
            $this->laboratoryResult,
            $this->returnReason,
            auth()->user(),
        );
        $this->reloadResult();
        Notifier::success('laboratory_results.returned');
    }

    public function render(LaboratoryReportService $reports): View
    {
        return view('livewire.laboratory.verify-result', [
            'reportEligible' => $reports->isEligible($this->laboratoryResult->order),
        ])->layout('components.layouts.app', [
            'title' => 'Hakiki Matokeo',
            'description' => $this->laboratoryResult->test->name,
        ]);
    }

    private function reloadResult(): void
    {
        $this->laboratoryResult = $this->laboratoryResult->refresh()->load([
            'order.patient',
            'order.items.results',
            'test',
            'values',
            'sample',
        ]);
    }
}
