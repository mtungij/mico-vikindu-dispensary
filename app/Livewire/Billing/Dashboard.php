<?php

namespace App\Livewire\Billing;

use App\Models\CashierSession;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Dashboard extends Component
{
    public function mount(): void
    {
        Gate::authorize('billing.view-dashboard');
    }

    public function render(): View
    {
        $facilityId = currentFacility()?->id;
        $user = auth()->user();

        return view('livewire.billing.dashboard', [
            'stats' => [
                'awaiting_payment' => [
                    'value' => Invoice::query()->where('facility_id', $facilityId)->where('balance_amount', '>', 0)->count(),
                    'url' => $user?->can('billing.view-queue') ? route('billing.index', ['tab' => 'awaiting']) : null,
                ],
                'partially_paid' => [
                    'value' => Invoice::query()->where('facility_id', $facilityId)->where('payment_status', 'partial')->count(),
                    'url' => $user?->can('billing.view-queue') ? route('billing.index', ['tab' => 'partial']) : null,
                ],
                'paid_today' => [
                    'value' => Invoice::query()->where('facility_id', $facilityId)->where('payment_status', 'paid')->whereDate('updated_at', today())->count(),
                    'url' => $user?->can('billing.view-queue') ? route('billing.index', ['tab' => 'paid_today']) : null,
                ],
                'collected_today' => [
                    'value' => Payment::query()->where('facility_id', $facilityId)->where('status', 'confirmed')->whereDate('payment_date', today())->sum('amount'),
                    'url' => $user?->can('billing.reports.view') ? route('reports.billing.payments') : null,
                ],
                'outstanding' => [
                    'value' => Invoice::query()->where('facility_id', $facilityId)->sum('balance_amount'),
                    'url' => $user?->can('billing.view-queue') ? route('billing.index', ['tab' => 'awaiting']) : null,
                ],
                'active_sessions' => [
                    'value' => CashierSession::query()->where('facility_id', $facilityId)->where('status', 'open')->count(),
                    'url' => $user?->can('cashier.sessions.view') ? route('cashier.sessions.index') : null,
                ],
            ],
            'recent' => Payment::query()
                ->where('facility_id', $facilityId)
                ->with(['invoice.patient', 'method'])
                ->latest()
                ->limit(8)
                ->get(),
        ])->layout('components.layouts.app', [
            'title' => 'Billing Dashboard',
            'description' => 'Muhtasari wa malipo, invoice na cashier sessions.',
        ]);
    }
}
