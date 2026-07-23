<?php

namespace App\Livewire\Laboratory;

use App\Livewire\Forms\LaboratorySampleCollectionForm;
use App\Models\LaboratoryOrder;
use App\Models\LaboratoryOrderItem;
use App\Services\LaboratorySampleService;
use App\Support\Notifier;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class Queue extends Component
{
    use WithPagination;

    public string $tab = 'awaiting_sample';

    public string $search = '';

    public ?LaboratoryOrder $selectedOrder = null;

    public LaboratorySampleCollectionForm $sampleForm;

    public bool $showCollectModal = false;

    public function mount(): void
    {
        Gate::authorize('laboratory.view-queue');
    }

    public function openCollect(LaboratoryOrder $order, ?int $itemId = null): void
    {
        Gate::authorize('laboratory.collect-sample');
        abort_unless($order->facility_id === currentFacility()?->id, 404);
        $this->resetErrorBag();
        $this->selectedOrder = $order->load(['items.laboratoryTest.specimenType', 'items.sample']);
        $this->sampleForm->resetForm();
        if ($itemId && $this->selectedOrder->items->contains(fn ($item): bool => $item->id === $itemId && $item->sample_id === null)) {
            $this->sampleForm->order_item_ids = [$itemId];
        }
        $this->showCollectModal = true;
    }

    public function collectAndAccept(LaboratorySampleService $service): void
    {
        try {
            Gate::authorize('laboratory.collect-sample');
            Gate::authorize('laboratory.accept-sample');
            if (! $this->selectedOrder) {
                throw ValidationException::withMessages(['order' => 'Mgonjwa hana order inayoweza kushughulikiwa.']);
            }
            $this->sampleForm->validate();
            $selectedCount = count($this->sampleForm->order_item_ids);
            $service->collectSample($this->selectedOrder, $this->sampleForm->normalize(), auth()->user(), true);
            $remainingCount = $this->selectedOrder->items()
                ->whereNull('sample_id')
                ->whereIn('status', ['ordered', 'awaiting_payment', 'ready_for_collection', 'pending_collection'])
                ->count();
            $this->sampleForm->resetForm();
            $this->showCollectModal = false;
            $this->selectedOrder = null;
            $this->resetPage();
            $message = $remainingCount > 0
                ? "Sampuli za vipimo {$selectedCount} zimekubaliwa. Vipimo {$remainingCount} bado vinasubiri sample collection."
                : 'Sampuli imekusanywa na kukubaliwa. Kipimo sasa kinapatikana kwenye Processing kwa kuingiza matokeo.';
            Notifier::success($message);
        } catch (ValidationException $exception) {
            $this->dispatch('laboratory-validation-failed', field: array_key_first($exception->errors()));
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('Laboratory sample collection failed.', [
                'order_id' => $this->selectedOrder?->id,
                'user_id' => auth()->id(),
                'exception' => $exception,
            ]);
            $authorizationFailure = $exception instanceof AuthorizationException
                || ($exception instanceof HttpExceptionInterface && $exception->getStatusCode() === 403);
            $message = $authorizationFailure
                ? ($exception->getMessage() ?: 'Huna ruhusa ya kukusanya sampuli.')
                : 'Sampuli haikuweza kukusanywa. Tafadhali jaribu tena au wasiliana na msimamizi.';
            $this->addError('action', $message);
            Notifier::error($message);
        }
    }

    public function render(): View
    {
        $processablePayments = ['paid', 'covered', 'waived', 'not_required'];
        $canOverridePayment = auth()->user()->can('laboratory.override-payment');
        $orders = LaboratoryOrder::query()->forCurrentFacility()->with(['patient', 'items.laboratoryTest.specimenType', 'items.sample', 'items.results'])
            ->when($this->search, fn ($q) => $q->where(fn ($searchQuery) => $searchQuery->where('order_number', 'like', "%{$this->search}%")->orWhereHas('patient', fn ($p) => $p->where('first_name', 'like', "%{$this->search}%")->orWhere('last_name', 'like', "%{$this->search}%")->orWhere('patient_number', 'like', "%{$this->search}%"))))
            ->when($this->tab === 'awaiting_payment', fn ($q) => $q->where('payment_status', 'pending'))
            ->when($this->tab === 'awaiting_sample', fn ($q) => $q
                ->when(! $canOverridePayment, fn ($orders) => $orders->whereIn('payment_status', $processablePayments))
                ->whereHas('items', fn ($items) => $items->whereNull('sample_id')->whereIn('status', ['ordered', 'awaiting_payment', 'ready_for_collection', 'pending_collection'])))
            ->when($this->tab === 'processing', fn ($q) => $q->when(! $canOverridePayment, fn ($orders) => $orders->whereIn('payment_status', $processablePayments))->whereHas('items', fn ($items) => $items
                ->whereNotNull('sample_id')
                ->whereIn('status', ['sample_collected', 'sample_accepted', 'processing'])
                ->whereHas('sample', fn ($sample) => $sample->where('sample_status', 'accepted'))
                ->where(fn ($results) => $results->whereNull('result_status')->orWhereIn('result_status', ['draft', 'entered']))))
            ->when($this->tab === 'pending_verification', fn ($q) => $q->whereHas('items', fn ($items) => $items->where('result_status', 'pending_verification')))
            ->when($this->tab === 'completed', fn ($q) => $q->whereHas('items', fn ($items) => $items->whereIn('result_status', ['verified', 'released'])))
            ->latest('ordered_at')->paginate(10);

        $items = LaboratoryOrderItem::query()->whereHas('order', fn ($order) => $order->forCurrentFacility());
        $tabCounts = [
            'awaiting_payment' => (clone $items)->whereHas('order', fn ($order) => $order->where('payment_status', 'pending'))->count(),
            'awaiting_sample' => (clone $items)->whereNull('sample_id')->whereIn('status', ['ordered', 'awaiting_payment', 'ready_for_collection', 'pending_collection'])->when(! $canOverridePayment, fn ($query) => $query->whereHas('order', fn ($order) => $order->whereIn('payment_status', $processablePayments)))->count(),
            'processing' => (clone $items)->whereNotNull('sample_id')->whereIn('status', ['sample_collected', 'sample_accepted', 'processing'])->where(fn ($query) => $query->whereNull('result_status')->orWhereIn('result_status', ['draft', 'entered']))->whereHas('sample', fn ($sample) => $sample->where('sample_status', 'accepted'))->when(! $canOverridePayment, fn ($query) => $query->whereHas('order', fn ($order) => $order->whereIn('payment_status', $processablePayments)))->count(),
            'pending_verification' => (clone $items)->where('result_status', 'pending_verification')->count(),
            'completed' => (clone $items)->whereIn('result_status', ['verified', 'released'])->count(),
        ];

        return view('livewire.laboratory.queue', ['orders' => $orders, 'tabCounts' => $tabCounts])->layout('components.layouts.app', ['title' => 'Foleni ya Maabara', 'description' => 'Orders, sample collection na result workflow.']);
    }
}
