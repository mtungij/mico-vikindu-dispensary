<div class="space-y-4">
    <x-card>
        <div class="grid gap-3 text-sm sm:grid-cols-4">
            <div><span class="text-slate-500">Cashier</span><p class="font-semibold">{{ $cashierSession->cashier?->name }}</p></div>
            <div><span class="text-slate-500">Status</span><p class="font-semibold">{{ $cashierSession->status }}</p></div>
            <div><span class="text-slate-500">Expected Cash</span><p class="font-semibold">{{ number_format($expected, 2) }}</p></div>
            <div><span class="text-slate-500">Variance</span><p class="font-semibold">{{ number_format($cashierSession->variance ?? 0, 2) }}</p></div>
        </div>
        <div class="mt-4">
            <a class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold dark:border-slate-700" href="{{ route('cashier.sessions.print', $cashierSession) }}" target="_blank">Print Session</a>
        </div>
    </x-card>

    <x-card>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <tbody>
                    @forelse($cashierSession->payments as $payment)
                        <tr class="border-t border-slate-100 dark:border-slate-800">
                            <td class="px-3 py-3">{{ $payment->payment_number }}</td>
                            <td class="px-3 py-3">{{ $payment->invoice?->invoice_number }}</td>
                            <td class="px-3 py-3">{{ $payment->method?->name }}</td>
                            <td class="px-3 py-3 text-right">{{ number_format($payment->amount, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td class="px-3 py-8 text-center text-slate-500">Hakuna payments kwenye session hii.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</div>
