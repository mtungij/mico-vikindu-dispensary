<?php

namespace App\Livewire\Billing;

use App\Models\Invoice;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class Queue extends Component
{
    use WithPagination;

    public string $tab = 'awaiting';
    public string $search = '';

    protected array $queryString = [
        'tab' => ['except' => 'awaiting'],
        'search' => ['except' => ''],
    ];

    public function mount(): void
    {
        Gate::authorize('billing.view-queue');

        if (! in_array($this->tab, ['awaiting', 'partial', 'paid_today', 'all'], true)) {
            $this->tab = 'awaiting';
        }
    }

    public function updatedTab(): void
    {
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        return view('livewire.billing.queue', [
            'invoices' => Invoice::query()
                ->forCurrentFacility()
                ->with(['patient', 'visit', 'patientPayerProfile'])
                ->when($this->tab === 'awaiting', fn (Builder $query) => $query->where('balance_amount', '>', 0))
                ->when($this->tab === 'partial', fn (Builder $query) => $query->where('payment_status', 'partial'))
                ->when($this->tab === 'paid_today', fn (Builder $query) => $query->where('payment_status', 'paid')->whereDate('updated_at', today()))
                ->when($this->search, function (Builder $query): void {
                    $search = '%'.$this->search.'%';

                    $query->where(function (Builder $query) use ($search): void {
                        $query->where('invoice_number', 'like', $search)
                            ->orWhereHas('patient', function (Builder $patient) use ($search): void {
                                $patient->where('first_name', 'like', $search)
                                    ->orWhere('last_name', 'like', $search)
                                    ->orWhere('patient_number', 'like', $search);
                            });
                    });
                })
                ->latest()
                ->paginate(12),
        ])->layout('components.layouts.app', [
            'title' => 'Billing Queue',
            'description' => 'Foleni ya wagonjwa wanaohitaji malipo au clearance.',
        ]);
    }
}
