<div class="space-y-4">
    <x-card>
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-sm text-slate-500">Session Number</p>
                <h3 class="text-lg font-semibold">{{ $cashierSession->session_number }}</h3>
                <p class="mt-1 text-sm text-slate-500">{{ $cashierSession->cashier?->name }} · {{ str($cashierSession->shift ?? 'morning')->headline() }}</p>
            </div>
            <div class="flex gap-2">
                <a class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold dark:border-slate-700" href="{{ route('cashier.sessions.print', $cashierSession) }}" target="_blank">Print Session</a>
                @if($cashierSession->status === 'open')
                    <a class="rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white" href="{{ route('billing.index') }}">Receive Payment</a>
                @endif
            </div>
        </div>

        <div class="mt-5 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
            <div><span class="text-slate-500">Status</span><p class="font-semibold">{{ $cashierSession->status }}</p></div>
            <div><span class="text-slate-500">Opening Time</span><p class="font-semibold">{{ $cashierSession->opened_at?->format('Y-m-d H:i') }}</p></div>
            <div><span class="text-slate-500">Opening Float</span><p class="font-semibold">{{ number_format($cashierSession->opening_float, 2) }}</p></div>
            <div><span class="text-slate-500">Cash Drawer</span><p class="font-semibold">{{ $cashierSession->cash_drawer ?? 'Main Counter' }}</p></div>
            <div><span class="text-slate-500">Cash Payments</span><p class="font-semibold">{{ number_format($totals['cash'] ?? 0, 2) }}</p></div>
            <div><span class="text-slate-500">Mobile Money</span><p class="font-semibold">{{ number_format($totals['mobile_money'] ?? 0, 2) }}</p></div>
            <div><span class="text-slate-500">Card</span><p class="font-semibold">{{ number_format($totals['card'] ?? 0, 2) }}</p></div>
            <div><span class="text-slate-500">Bank</span><p class="font-semibold">{{ number_format($totals['bank_transfer'] ?? $totals['bank'] ?? 0, 2) }}</p></div>
            <div><span class="text-slate-500">Cheque</span><p class="font-semibold">{{ number_format($totals['cheque'] ?? 0, 2) }}</p></div>
            <div><span class="text-slate-500">Expected Cash</span><p class="font-semibold">{{ number_format($expected, 2) }}</p></div>
            <div><span class="text-slate-500">Payments</span><p class="font-semibold">{{ $totals['payments_count'] ?? 0 }}</p></div>
            <div><span class="text-slate-500">Receipts</span><p class="font-semibold">{{ $totals['receipts_count'] ?? 0 }}</p></div>
        </div>
    </x-card>

    <x-card>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="text-left text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-3 py-2">Payment</th>
                        <th class="px-3 py-2">Invoice</th>
                        <th class="px-3 py-2">Method</th>
                        <th class="px-3 py-2 text-right">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($cashierSession->payments as $payment)
                        <tr class="border-t border-slate-100 dark:border-slate-800">
                            <td class="px-3 py-3">{{ $payment->payment_number }}</td>
                            <td class="px-3 py-3">{{ $payment->invoice?->invoice_number }}</td>
                            <td class="px-3 py-3">{{ $payment->method?->name }}</td>
                            <td class="px-3 py-3 text-right">{{ number_format($payment->amount, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-3 py-8 text-center text-slate-500">Hakuna payments kwenye session hii.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</div>
