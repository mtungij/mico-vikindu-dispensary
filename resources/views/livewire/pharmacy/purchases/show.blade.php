<div class="space-y-6">
    <x-card class="grid gap-4 md:grid-cols-4">
        <div><p class="text-xs text-slate-500">Supplier</p><p class="font-semibold">{{ $order->supplier?->name }}</p></div>
        <div><p class="text-xs text-slate-500">Status</p><p class="font-semibold">{{ $order->status?->value ?? $order->status }}</p></div>
        <div><p class="text-xs text-slate-500">Order Date</p><p class="font-semibold">{{ $order->order_date?->format('d M Y') }}</p></div>
        <div><p class="text-xs text-slate-500">Total</p><p class="font-semibold">{{ number_format((float) $order->grand_total, 2) }}</p></div>
    </x-card>
    <x-card>
        <div class="overflow-x-auto"><table class="min-w-full text-sm"><thead><tr class="text-left text-xs uppercase text-slate-500"><th class="py-3">Medicine</th><th>Qty</th><th>Unit Cost</th><th>Total</th></tr></thead><tbody>
            @foreach ($order->items as $item)
                <tr class="border-t border-slate-100 dark:border-slate-800"><td class="py-3 font-semibold">{{ $item->medicine?->name }}</td><td>{{ $item->quantity_ordered }}</td><td>{{ number_format((float) $item->unit_cost, 2) }}</td><td>{{ number_format((float) $item->line_total, 2) }}</td></tr>
            @endforeach
        </tbody></table></div>
    </x-card>
</div>
