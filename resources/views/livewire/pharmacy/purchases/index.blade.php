<div class="space-y-6">
    <x-card>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead><tr class="text-left text-xs uppercase text-slate-500"><th class="py-3">PO Number</th><th>Supplier</th><th>Date</th><th>Status</th><th>Total</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr class="border-t border-slate-100 dark:border-slate-800">
                            <td class="py-3 font-semibold">{{ $row->purchase_order_number }}</td>
                            <td>{{ $row->supplier?->name }}</td>
                            <td>{{ $row->order_date?->format('d M Y') }}</td>
                            <td><x-badge tone="blue">{{ $row->status?->value ?? $row->status }}</x-badge></td>
                            <td>{{ number_format((float) $row->grand_total, 2) }}</td>
                            <td class="text-right"><a href="{{ route('pharmacy.purchase-orders.show', $row) }}" class="rounded-md p-2 hover:bg-slate-100 dark:hover:bg-slate-800"><x-lucide-eye class="h-4 w-4" /></a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-8 text-center text-slate-500">Hakuna purchase orders.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $rows->links() }}</div>
    </x-card>
</div>
