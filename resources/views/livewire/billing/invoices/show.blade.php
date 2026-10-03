<div class="space-y-4">
    <div class="grid gap-4 lg:grid-cols-4">
        <x-card>
            <p class="text-sm text-slate-500">Patient</p>
            <p class="font-semibold">{{ $invoice->patient?->first_name }} {{ $invoice->patient?->last_name }}</p>
        </x-card>
        <x-card>
            <p class="text-sm text-slate-500">Patient Amount</p>
            <p class="font-semibold">{{ number_format($invoice->patient_amount, 2) }}</p>
        </x-card>
        <x-card>
            <p class="text-sm text-slate-500">Paid</p>
            <p class="font-semibold">{{ number_format($invoice->paid_amount, 2) }}</p>
        </x-card>
        <x-card>
            <p class="text-sm text-slate-500">Balance</p>
            <p class="font-semibold">{{ number_format($invoice->balance_amount, 2) }}</p>
        </x-card>
    </div>

    <x-card>
        <div class="flex items-center justify-between gap-3">
            <h3 class="font-semibold">Invoice Items</h3>
            <div class="flex gap-2">
                <a href="{{ route('billing.invoices.print', $invoice) }}" class="rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700">Print</a>
                @can('create', \App\Models\Payment::class)
                    <button type="button" wire:click="openPaymentModal" class="rounded-md bg-primary px-3 py-2 text-sm font-semibold text-white">Receive Payment</button>
                @endcan
            </div>
        </div>

        <div class="mt-3 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-left text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-3 py-2">Item</th>
                        <th class="px-3 py-2">Qty</th>
                        <th class="px-3 py-2">Gross</th>
                        <th class="px-3 py-2">Patient</th>
                        <th class="px-3 py-2">Paid</th>
                        <th class="px-3 py-2">Balance</th>
                        <th class="px-3 py-2">Covered Qty</th>
                        <th class="px-3 py-2">Insurance</th>
                        <th class="px-3 py-2">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($invoice->items as $item)
                        @php
                            $medicineFinancial = $item->prescriptionItem ? app(\App\Services\MedicineFinancialClearanceService::class)->forItem($item->prescriptionItem) : null;
                        @endphp
                        <tr class="border-t border-slate-100 dark:border-slate-800">
                            <td class="px-3 py-2">{{ $item->description_snapshot ?? $item->description }}@if($medicineFinancial)<div class="text-xs text-slate-500">Unit price: {{ number_format((float) $medicineFinancial['billed_unit_price'], 2) }}</div>@endif</td>
                            <td class="px-3 py-2">{{ $item->quantity }}</td>
                            <td class="px-3 py-2">{{ number_format($item->gross_amount ?: $item->total_amount, 2) }}</td>
                            <td class="px-3 py-2">{{ number_format($item->patient_amount, 2) }}</td>
                            <td class="px-3 py-2">{{ number_format($item->paid_amount, 2) }}</td>
                            <td class="px-3 py-2">{{ number_format(max(0, (float) $item->patient_amount - (float) $item->paid_amount), 2) }}</td>
                            <td class="px-3 py-2">@if($medicineFinancial){{ rtrim(rtrim($medicineFinancial['paid_quantity'], '0'), '.') }} / {{ rtrim(rtrim($medicineFinancial['prescribed_quantity'], '0'), '.') }}@else—@endif</td>
                            <td class="px-3 py-2">{{ number_format($item->insurance_amount, 2) }}</td>
                            <td class="px-3 py-2">{{ $medicineFinancial && (float) $medicineFinancial['paid_quantity'] > 0 && ! $medicineFinancial['financially_cleared'] ? 'Partially Paid' : $item->status }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-card>

    <x-card>
        <h3 class="font-semibold">Payments and Receipts</h3>
        <div class="mt-3 divide-y divide-slate-100 dark:divide-slate-800">
            @forelse($invoice->payments as $payment)
                <div class="flex justify-between py-3 text-sm">
                    <span>{{ $payment->payment_number }} - {{ $payment->method?->name }}</span>
                    <span>{{ number_format($payment->amount, 2) }}</span>
                </div>
            @empty
                <p class="py-6 text-sm text-slate-500">Hakuna malipo.</p>
            @endforelse
        </div>
    </x-card>

    @if($showPaymentModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" role="dialog" aria-modal="true">
            <div class="max-h-[90vh] w-full max-w-5xl overflow-y-auto rounded-md bg-white p-5 shadow-xl dark:bg-card-dark">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h3 class="font-semibold">Receive Payment</h3>
                    <button type="button" wire:click="closePaymentModal" class="rounded-md p-2 hover:bg-slate-100 dark:hover:bg-slate-800">
                        <x-lucide-x class="h-5 w-5" />
                    </button>
                </div>

                <form wire:submit="confirmPayment" class="space-y-4">
                    @error('payment')
                        <p class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-300">{{ $message }}</p>
                    @enderror

                    <div class="grid grid-cols-2 gap-3 rounded-md bg-slate-50 p-3 text-sm dark:bg-slate-900">
                        <div><span class="block text-xs text-slate-500">Invoice total</span><strong>{{ number_format($invoice->patient_amount, 2) }}</strong></div>
                        <div><span class="block text-xs text-slate-500">Previously paid</span><strong>{{ number_format($invoice->paid_amount, 2) }}</strong></div>
                        <div><span class="block text-xs text-slate-500">Payment now</span><strong>{{ number_format((float) $amount, 2) }}</strong></div>
                        <div><span class="block text-xs text-slate-500">Remaining balance</span><strong>{{ number_format(max(0, (float) $invoice->balance_amount - (float) $amount), 2) }}</strong></div>
                    </div>

                    <div class="overflow-x-auto">
                        <p class="mb-2 text-sm">Chagua huduma zinazolipiwa sasa. Unselected items remain awaiting payment.</p>
                        <table class="min-w-full text-left text-sm">
                            <thead><tr><th>Service / Select</th><th>Patient total</th><th>Previously paid</th><th>Outstanding</th><th>Pay now (TSh)</th></tr></thead>
                            <tbody>
                            @foreach($invoice->items->groupBy('item_type') as $type => $items)
                                <tr><th colspan="5" class="pt-3 text-xs uppercase text-slate-500">{{ str_replace('_', ' ', $type) }}</th></tr>
                                @foreach($items as $item)
                                    @php
                                        $outstanding = max(0, (float) $item->patient_amount - (float) $item->paid_amount);
                                        $inactive = in_array($item->status, ['cancelled', 'reversed', 'non_billable'], true);
                                        $selected = in_array((string) $item->id, array_map('strval', $selectedItems), true);
                                    @endphp
                                    <tr wire:key="payment-item-{{ $item->id }}" class="border-b border-slate-200">
                                        <td class="py-2"><label class="flex items-center gap-2"><input type="checkbox" wire:model.live="selectedItems" value="{{ $item->id }}" @disabled($inactive || $outstanding <= 0)><span>{{ $item->description }}<span class="block text-xs text-slate-500">{{ $inactive ? ucfirst($item->status) : ($outstanding <= 0 ? 'PAID / CLEARED' : ((float) $item->paid_amount > 0 ? 'PARTIALLY PAID' : 'UNPAID')) }}</span></span></label></td>
                                        <td>{{ number_format($item->patient_amount, 2) }}</td>
                                        <td>{{ number_format($item->paid_amount, 2) }}</td>
                                        <td>{{ number_format($outstanding, 2) }}</td>
                                        <td class="py-2">
                                            @if(! $inactive && $outstanding > 0)
                                                <input aria-label="Pay now for {{ $item->description }}" type="number" min="0.01" max="{{ $outstanding }}" step="0.01" wire:model.live.debounce.300ms="itemAmounts.{{ $item->id }}" @disabled(! $selected) class="w-32 rounded border-slate-300 dark:bg-slate-900">
                                                @if($item->prescriptionItem)
                                                    <label class="mt-1 block text-xs">Or medicine quantity to pay for
                                                        <input type="number" min="{{ $item->prescriptionItem->medicine?->dispensingUnit?->decimal_allowed ? '0.001' : '1' }}" step="{{ $item->prescriptionItem->medicine?->dispensingUnit?->decimal_allowed ? '0.001' : '1' }}" wire:model.live.debounce.300ms="medicineQuantities.{{ $item->id }}" @disabled(! $selected) class="w-24 rounded border-slate-300 dark:bg-slate-900">
                                                    </label>
                                                @endif
                                            @else
                                                {{ $inactive ? '—' : 'PAID / CLEARED' }}
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            @endforeach
                            </tbody>
                        </table>
                        <p class="mt-3 font-semibold">Selected total: TSh {{ number_format($this->selectedTotal(), 2) }}</p>
                        @error('allocations') <p role="alert" class="text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <label class="block">
                        <span class="text-sm">Method</span>
                        <select wire:model.live="payment_method_id" class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
                            <option value="">Select</option>
                            @foreach($methods as $method)
                                <option value="{{ $method->id }}">{{ $method->name }}</option>
                            @endforeach
                        </select>
                        @error('payment_method_id')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </label>

                    <label class="block">
                        <span class="text-sm">Amount received (must equal selected total)</span>
                        <input type="number" step="0.01" min="0.01" wire:model.live="amount" class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
                        @error('amount')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </label>

                    <label class="block">
                        <span class="text-sm">Reference</span>
                        <input type="text" wire:model="transaction_reference" class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
                        @error('transaction_reference')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </label>

                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="closePaymentModal" class="rounded-md border border-slate-300 px-4 py-2 text-sm dark:border-slate-700">Cancel</button>
                        <x-primary-button type="submit" wire:loading.attr="disabled" wire:target="confirmPayment">
                            <span wire:loading.remove wire:target="confirmPayment">Confirm Payment</span>
                            <span wire:loading wire:target="confirmPayment">Inahifadhi...</span>
                        </x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if($showCashierSessionModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" role="dialog" aria-modal="true">
            <div class="w-full max-w-md rounded-md bg-white p-5 shadow-xl dark:bg-card-dark">
                <h3 class="mb-4 font-semibold">Open Cashier Session</h3>
                <form wire:submit="openCashierSession" class="space-y-4">
                    @error('session')
                        <p class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-300">{{ $message }}</p>
                    @enderror
                    <label class="block">
                        <span class="text-sm">Shift</span>
                        <select wire:model="cashierSessionForm.shift" class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
                            <option value="morning">Morning</option>
                            <option value="afternoon">Afternoon</option>
                            <option value="evening">Evening</option>
                            <option value="night">Night</option>
                            <option value="custom">Custom</option>
                        </select>
                        @error('cashierSessionForm.shift')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </label>
                    <label class="block">
                        <span class="text-sm">Opening Float</span>
                        <input type="number" step="0.01" min="0" wire:model="cashierSessionForm.opening_float" class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
                        @error('cashierSessionForm.opening_float')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </label>
                    <label class="block">
                        <span class="text-sm">Cash Drawer</span>
                        <input type="text" wire:model="cashierSessionForm.cash_drawer" class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-900">
                        @error('cashierSessionForm.cash_drawer')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </label>
                    <label class="block">
                        <span class="text-sm">Notes</span>
                        <textarea rows="3" wire:model="cashierSessionForm.notes" class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-900"></textarea>
                        @error('cashierSessionForm.notes')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </label>
                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="closeCashierSessionModal" class="rounded-md border border-slate-300 px-4 py-2 text-sm dark:border-slate-700">Cancel</button>
                        <x-primary-button type="submit" wire:loading.attr="disabled" wire:target="openCashierSession">
                            <span wire:loading.remove wire:target="openCashierSession">Open Session</span>
                            <span wire:loading wire:target="openCashierSession">Inafungua...</span>
                        </x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
