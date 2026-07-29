<?php

namespace App\Livewire\Laboratory;

use App\Models\LaboratoryOrder;
use App\Services\LaboratoryReportService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class OrderShow extends Component
{
    public LaboratoryOrder $laboratoryOrder;

    public string $tab = 'summary';

    public function mount(LaboratoryOrder $laboratoryOrder): void
    {
        Gate::authorize('laboratory.view-order');
        abort_unless($laboratoryOrder->facility_id === currentFacility()?->id, 404);
        $this->laboratoryOrder = $laboratoryOrder;
    }

    public function render(LaboratoryReportService $reports): View
    {
        $order = $this->laboratoryOrder->load(['patient', 'visit', 'items.laboratoryTest', 'items.sample', 'samples.items', 'results.values']);

        return view('livewire.laboratory.order-show', [
            'order' => $order,
            'reportEligible' => $reports->isEligible($order),
        ])->layout('components.layouts.app', ['title' => $this->laboratoryOrder->order_number, 'description' => 'Laboratory order details.']);
    }
}
