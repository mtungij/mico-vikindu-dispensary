<?php

namespace App\Livewire\Laboratory;

use App\Livewire\Forms\LaboratorySampleCollectionForm;
use App\Models\LaboratoryOrder;
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

    public function openCollect(LaboratoryOrder $order): void
    {
        Gate::authorize('laboratory.collect-sample');
        abort_unless($order->facility_id === currentFacility()?->id, 404);
        $this->resetErrorBag();
        $this->selectedOrder = $order;
        $this->sampleForm->resetForm();
        $this->sampleForm->order_item_ids = $order->items()->pluck('id')->map(fn ($id): int => (int) $id)->all();
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
            $service->collectSample($this->selectedOrder, $this->sampleForm->normalize(), auth()->user(), true);
            $this->showCollectModal = false;
            $this->selectedOrder = null;
            $this->resetPage();
            Notifier::success('Sampuli imekusanywa na kukubaliwa kikamilifu.');
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
        $orders = LaboratoryOrder::query()->forCurrentFacility()->with(['patient', 'items.laboratoryTest', 'items.sample'])
            ->when($this->search, fn ($q) => $q->where(fn ($searchQuery) => $searchQuery->where('order_number', 'like', "%{$this->search}%")->orWhereHas('patient', fn ($p) => $p->where('first_name', 'like', "%{$this->search}%")->orWhere('last_name', 'like', "%{$this->search}%")->orWhere('patient_number', 'like', "%{$this->search}%"))))
            ->when($this->tab === 'awaiting_payment', fn ($q) => $q->where('payment_status', 'pending'))
            ->when($this->tab === 'awaiting_sample', fn ($q) => $q->where('status', 'ordered')->whereIn('payment_status', ['paid', 'covered', 'waived', 'not_required']))
            ->when($this->tab === 'processing', fn ($q) => $q->whereIn('status', ['sample_pending', 'processing']))
            ->when($this->tab === 'completed', fn ($q) => $q->where('status', 'completed'))
            ->latest('ordered_at')->paginate(10);

        return view('livewire.laboratory.queue', ['orders' => $orders])->layout('components.layouts.app', ['title' => 'Foleni ya Maabara', 'description' => 'Orders, sample collection na result workflow.']);
    }
}
