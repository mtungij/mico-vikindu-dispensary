<?php

namespace App\Livewire\Laboratory;

use App\Models\LaboratoryOrder;
use App\Models\LaboratoryOrderItem;
use App\Services\LaboratoryResultService;
use App\Support\Notifier;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class ResultEntry extends Component
{
    public LaboratoryOrder $laboratoryOrder;

    public ?int $itemId = null;

    public array $values = [];

    public ?string $comments = null;

    public function mount(LaboratoryOrder $laboratoryOrder): void
    {
        Gate::authorize('laboratory-results.enter');
        abort_unless($laboratoryOrder->facility_id === currentFacility()?->id, 404);
        $this->laboratoryOrder = $laboratoryOrder;
        $firstItemId = $laboratoryOrder->items()->whereNotNull('laboratory_test_id')->value('id');
        if ($firstItemId) {
            $this->selectItem($firstItemId);
        }
    }

    public function selectItem(int $itemId): void
    {
        $item = $this->laboratoryOrder->items()->with('results.values')->findOrFail($itemId);
        $this->itemId = $item->id;
        $this->values = [];
        $this->comments = null;
        $this->resetErrorBag();

        $result = $item->results->sortByDesc('result_version')->first(fn ($candidate) => in_array($candidate->result_status->value, ['draft', 'entered'], true));
        if (! $result) {
            return;
        }
        foreach ($result->values as $value) {
            $key = (string) ($value->laboratory_test_parameter_id ?? 'main');
            $this->values[$key]['value'] = $value->numeric_value
                ?? $value->selected_value
                ?? $value->text_value
                ?? $value->boolean_value;
        }
        $this->comments = $result->comments;
    }

    public function saveDraft(LaboratoryResultService $service): void
    {
        $this->store($service, false);
    }

    public function submitForVerification(LaboratoryResultService $service): void
    {
        $this->store($service, true);
    }

    /** Backwards-compatible action name for any stale Livewire clients. */
    public function submit(LaboratoryResultService $service): void
    {
        $this->submitForVerification($service);
    }

    public function render(): View
    {
        return view('livewire.laboratory.result-entry', [
            'order' => $this->laboratoryOrder->load(['patient', 'items.laboratoryTest.parameters', 'items.sample']),
            'selectedItem' => $this->item(false),
        ])->layout('components.layouts.app', ['title' => 'Ingiza Matokeo', 'description' => $this->laboratoryOrder->order_number]);
    }

    private function store(LaboratoryResultService $service, bool $submit): void
    {
        try {
            $item = $this->item();
            $service->saveForItem($item, $this->values + ['comments' => $this->comments], auth()->user(), $submit);
            $this->laboratoryOrder->refresh();
            $this->resetErrorBag();
            Notifier::success($submit ? 'Matokeo yametumwa kwa uthibitisho.' : 'Rasimu ya matokeo imehifadhiwa.');
        } catch (ValidationException $exception) {
            $field = array_key_first($exception->errors());
            $this->dispatch('laboratory-validation-failed', field: $field);
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('Laboratory result entry failed.', [
                'order_id' => $this->laboratoryOrder->id,
                'item_id' => $this->itemId,
                'user_id' => auth()->id(),
                'submit' => $submit,
                'exception' => $exception,
            ]);
            $authorizationFailure = $exception instanceof AuthorizationException
                || ($exception instanceof HttpExceptionInterface && $exception->getStatusCode() === 403);
            $message = $authorizationFailure
                ? ($exception->getMessage() ?: 'Huna ruhusa ya kutuma matokeo kwa uthibitisho.')
                : 'Matokeo hayakuweza kuhifadhiwa. Tafadhali jaribu tena au wasiliana na msimamizi.';
            $this->addError('action', $message);
            Notifier::error($message);
        }
    }

    private function item(bool $required = true): ?LaboratoryOrderItem
    {
        $query = $this->laboratoryOrder->items()->with(['laboratoryTest.parameters', 'sample']);

        return $required ? $query->findOrFail($this->itemId) : $query->find($this->itemId);
    }
}
