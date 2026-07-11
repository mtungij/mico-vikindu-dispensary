<?php

namespace App\Livewire\Pharmacy;

use App\Models\Dispensing;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\Prescription;
use App\Models\PurchaseReceipt;
use App\Models\StockTransfer;
use App\Services\MedicineStockService;
use App\Services\PharmacyReportService;
use App\Services\PharmacyReorderService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Dashboard extends Component
{
    public function mount(): void { Gate::authorize('pharmacy.view-dashboard'); }
    public function render(MedicineStockService $stock, PharmacyReorderService $reorder, PharmacyReportService $reports): View
    {
        return view('livewire.pharmacy.dashboard', [
            'stats' => [
                ['label' => 'Prescriptions Waiting', 'value' => Prescription::query()->forCurrentFacility()->whereIn('status', ['prescribed', 'awaiting_payment'])->count(), 'icon' => 'clipboard-list', 'tone' => 'amber'],
                ['label' => 'Dispensed Today', 'value' => Dispensing::query()->forCurrentFacility()->whereDate('dispensed_at', today())->count(), 'icon' => 'pill', 'tone' => 'green'],
                ['label' => 'Pharmacy Revenue Today', 'value' => number_format($reports->revenueToday()), 'icon' => 'receipt', 'tone' => 'blue'],
                ['label' => 'Low Stock Medicines', 'value' => $reorder->getLowStockMedicines()->count(), 'icon' => 'triangle-alert', 'tone' => 'red'],
                ['label' => 'Out of Stock', 'value' => $reorder->getOutOfStockMedicines()->count(), 'icon' => 'package-x', 'tone' => 'red'],
                ['label' => 'Expiring Batches', 'value' => MedicineBatch::query()->forCurrentFacility()->whereBetween('expiry_date', [today(), today()->addDays(90)])->count(), 'icon' => 'calendar-clock', 'tone' => 'amber'],
                ['label' => 'Expired Batches', 'value' => MedicineBatch::query()->forCurrentFacility()->whereDate('expiry_date', '<', today())->count(), 'icon' => 'circle-x', 'tone' => 'red'],
                ['label' => 'Pending Receipts', 'value' => PurchaseReceipt::query()->forCurrentFacility()->whereIn('status', ['draft', 'received'])->count(), 'icon' => 'package-check', 'tone' => 'indigo'],
                ['label' => 'Pending Transfers', 'value' => StockTransfer::query()->forCurrentFacility()->whereNotIn('status', ['received', 'cancelled'])->count(), 'icon' => 'arrow-right-left', 'tone' => 'blue'],
                ['label' => 'Stock Value', 'value' => number_format($stock->stockValue()), 'icon' => 'chart-no-axes-combined', 'tone' => 'green'],
            ],
            'recentDispensings' => Dispensing::query()->forCurrentFacility()->with('patient')->latest()->limit(8)->get(),
            'lowStock' => $reorder->getLowStockMedicines()->take(8),
            'expiring' => MedicineBatch::query()->forCurrentFacility()->with('medicine')->whereBetween('expiry_date', [today(), today()->addDays(90)])->limit(8)->get(),
        ])->layout('components.layouts.app', ['title' => 'Pharmacy Dashboard', 'description' => 'Muhtasari wa prescription, stock, expiry na revenue.']);
    }
}
