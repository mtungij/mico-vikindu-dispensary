<div class="space-y-4">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach($stats as $label => $value)
            <x-card><p class="text-sm text-slate-500">{{ str($label)->replace('_',' ')->title() }}</p><p class="mt-2 text-2xl font-semibold">{{ is_numeric($value) ? number_format((float)$value, str_contains($label,'collected') || str_contains($label,'outstanding') ? 2 : 0) : $value }}</p></x-card>
        @endforeach
    </div>
    <x-card><h3 class="font-semibold">Recent Payments</h3><div class="mt-3 divide-y divide-slate-100 dark:divide-slate-800">@forelse($recent as $payment)<a href="{{ route('billing.invoices.show',$payment->invoice_id) }}" class="flex justify-between py-3 text-sm"><span>{{ $payment->payment_number }} - {{ $payment->invoice?->patient?->first_name }} {{ $payment->invoice?->patient?->last_name }}</span><span>{{ number_format($payment->amount,2) }}</span></a>@empty<p class="py-6 text-sm text-slate-500">Hakuna malipo ya karibuni.</p>@endforelse</div></x-card>
</div>
