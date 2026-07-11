<?php

namespace App\Livewire\Reports;

use App\Services\PharmacyReportService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class Pharmacy extends Component
{
    use WithPagination;

    public string $type = 'stock-movement';

    public function mount(string $type = 'stock-movement'): void
    {
        Gate::authorize('pharmacy.reports.view');
        $this->type = $type;
    }

    public function render(PharmacyReportService $reports): View
    {
        $rows = $this->type === 'expiry-report'
            ? $reports->batches()->paginate(15)
            : $reports->stockMovements()->paginate(15);

        return view('livewire.reports.pharmacy', [
            'rows' => $rows,
            'type' => $this->type,
        ])->layout('components.layouts.app', [
            'title' => 'Pharmacy Reports',
            'description' => 'Stock movement, expiry na inventory reports.',
        ]);
    }
}
