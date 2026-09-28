<?php

namespace App\Livewire\Laboratory;

use App\Models\LaboratoryOrder;
use App\Services\LaboratoryOrderItemDecisionService;
use App\Services\LaboratoryReportService;
use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class OrderShow extends Component
{
    public LaboratoryOrder $laboratoryOrder;

    public string $tab = 'summary';

    public ?int $decidingItemId = null;

    public string $decision = 'not_performed';

    public string $reasonCode = '';

    public string $reasonDetail = '';

    public function beginDecision(int $itemId, string $decision): void
    {
        Gate::authorize('decideItem', $this->laboratoryOrder);
        abort_unless(in_array($decision, ['cancelled', 'not_performed'], true), 404);
        $item = $this->laboratoryOrder->items()->findOrFail($itemId);
        abort_unless(! in_array($item->status, ['completed', 'cancelled', 'not_performed', 'entered_in_error'], true)
            && ! in_array($item->result_status, ['pending_verification', 'verified', 'released'], true), 409);
        $this->decidingItemId = $itemId;
        $this->decision = $decision;
        $this->reasonCode = '';
        $this->reasonDetail = '';
    }

    public function decide(LaboratoryOrderItemDecisionService $service): void
    {
        Gate::authorize('decideItem', $this->laboratoryOrder);
        $this->validate([
            'decidingItemId' => ['required', 'integer'],
            'decision' => ['required', 'in:cancelled,not_performed'],
            'reasonCode' => ['required', 'in:'.implode(',', array_keys(LaboratoryOrderItemDecisionService::REASONS))],
            'reasonDetail' => ['nullable', 'string', 'max:2000'],
        ]);
        $item = $this->laboratoryOrder->items()->findOrFail($this->decidingItemId);
        $service->decide($item, $this->decision, $this->reasonCode, $this->reasonDetail, auth()->user());
        $this->decidingItemId = null;
        $this->laboratoryOrder->refresh();
        Notifier::success('Test decision recorded and billing reconciled.');
    }

    public function mount(LaboratoryOrder $laboratoryOrder): void
    {
        Gate::authorize('laboratory.view-order');
        abort_unless($laboratoryOrder->facility_id === currentFacility()?->id, 404);
        $this->laboratoryOrder = $laboratoryOrder;
    }

    public function render(LaboratoryReportService $reports): View
    {
        $order = $this->laboratoryOrder->load(['patient', 'visit', 'items.laboratoryTest', 'items.sample', 'items.invoiceItem', 'items.results', 'items.terminalDecider', 'samples.items', 'results.values']);

        return view('livewire.laboratory.order-show', [
            'order' => $order,
            'reportEligible' => $reports->isEligible($order),
        ])->layout('components.layouts.app', ['title' => $this->laboratoryOrder->order_number, 'description' => 'Laboratory order details.']);
    }
}
