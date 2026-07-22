<div class="space-y-4">
    <x-card>
        <div class="grid gap-3 md:grid-cols-3">
            <input wire:model.live.debounce.300ms="search" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" placeholder="Tafuta invoice au mgonjwa">
            <select wire:model.live="tab" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900">
                <option value="awaiting">Awaiting Payment</option>
                <option value="partial">Partially Paid</option>
                <option value="paid_today">Paid Today</option>
                <option value="all">All</option>
            </select>
            <a href="{{ route('cashier.dashboard') }}" class="inline-flex items-center justify-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white">
                <x-lucide-banknote class="h-4 w-4" /> Cashier
            </a>
        </div>
    </x-card>

    <x-card>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-left text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-3 py-2">Invoice / Visit</th>
                        <th class="px-3 py-2">Patient</th>
                        <th class="px-3 py-2">Payer</th>
                        <th class="px-3 py-2">Sources</th>
                        <th class="px-3 py-2">Departments</th>
                        <th class="px-3 py-2">Ordering Clinician</th>
                        <th class="px-3 py-2">Balance</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2">Date / Facility</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invoices as $invoice)
                        @php
                            $sources = $invoice->items->map(fn ($item) => match ($item->item_type) {
                                'registration' => 'Registration',
                                'consultation' => 'Consultation',
                                'laboratory_test', 'laboratory' => 'Laboratory',
                                'radiology', 'imaging' => 'Radiology',
                                'medicine', 'pharmacy' => 'Pharmacy',
                                'procedure' => 'Procedure',
                                default => str($item->item_type)->replace('_', ' ')->title()->toString(),
                            })->filter()->unique()->values();
                            $departments = $invoice->items->pluck('department.name')->filter()->unique()->values();
                            $clinicians = $invoice->items
                                ->map(fn ($item) => $item->laboratoryOrderItem?->order?->orderingClinician?->name)
                                ->filter()->unique()->values();
                        @endphp
                        <tr class="border-t border-slate-100 align-top dark:border-slate-800">
                            <td class="px-3 py-3">
                                <a class="font-semibold text-primary" href="{{ route('billing.invoices.show', $invoice) }}">{{ $invoice->invoice_number }}</a>
                                <div class="text-xs text-slate-500">{{ $invoice->visit?->visit_number ?? 'No visit' }}</div>
                            </td>
                            <td class="px-3 py-3">{{ $invoice->patient?->first_name }} {{ $invoice->patient?->last_name }}</td>
                            <td class="px-3 py-3">{{ $invoice->payer_type?->label() ?? str($invoice->payer_type)->title() }}</td>
                            <td class="px-3 py-3">
                                <div class="flex flex-wrap gap-1">
                                    @foreach($sources as $source)<x-badge tone="info">{{ $source }}</x-badge>@endforeach
                                </div>
                            </td>
                            <td class="px-3 py-3">{{ $departments->implode(', ') ?: ($sources->contains('Laboratory') ? 'Laboratory' : '—') }}</td>
                            <td class="px-3 py-3">{{ $clinicians->implode(', ') ?: '—' }}</td>
                            <td class="px-3 py-3 font-semibold">{{ number_format($invoice->balance_amount, 2) }}</td>
                            <td class="px-3 py-3">{{ str($invoice->payment_status)->replace('_', ' ')->title() }}</td>
                            <td class="px-3 py-3">
                                {{ $invoice->issued_at?->format('d/m/Y H:i') }}
                                <div class="text-xs text-slate-500">{{ $invoice->facility?->name }}</div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-3 py-8 text-center text-slate-500">Hakuna invoice kwenye foleni.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $invoices->links() }}</div>
    </x-card>
</div>
