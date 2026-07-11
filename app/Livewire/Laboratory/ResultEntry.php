<?php

namespace App\Livewire\Laboratory;

use App\Models\LaboratoryOrder;
use App\Models\LaboratoryOrderItem;
use App\Services\LaboratoryResultService;
use App\Support\Notifier;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class ResultEntry extends Component
{
    public LaboratoryOrder $laboratoryOrder; public ?int $itemId = null; public array $values = []; public ?string $comments = null;
    public function mount(LaboratoryOrder $laboratoryOrder): void { Gate::authorize('laboratory-results.enter'); abort_unless($laboratoryOrder->facility_id === currentFacility()?->id, 404); $this->laboratoryOrder = $laboratoryOrder; $this->itemId = $laboratoryOrder->items()->first()?->id; }
    public function saveDraft(LaboratoryResultService $service): void { $item = $this->item(); $result = $service->createDraft($item, auth()->user()); $service->saveValues($result, $this->values + ['comments' => $this->comments], auth()->user(), false); Notifier::success('laboratory_results.draft_saved'); }
    public function submit(LaboratoryResultService $service): void { $item = $this->item(); $result = $service->createDraft($item, auth()->user()); $service->saveValues($result, $this->values + ['comments' => $this->comments], auth()->user(), true); Notifier::success('laboratory_results.submitted'); }
    public function render(): View { return view('livewire.laboratory.result-entry', ['order' => $this->laboratoryOrder->load(['patient','items.laboratoryTest.parameters','items.sample']), 'selectedItem' => $this->item(false)])->layout('components.layouts.app', ['title' => 'Ingiza Matokeo', 'description' => $this->laboratoryOrder->order_number]); }
    private function item(bool $required = true): ?LaboratoryOrderItem { $q = $this->laboratoryOrder->items()->with(['laboratoryTest.parameters','sample']); return $required ? $q->findOrFail($this->itemId) : $q->find($this->itemId); }
}
