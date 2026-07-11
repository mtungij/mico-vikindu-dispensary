<div class="space-y-6">
    <div class="flex justify-end"><a href="{{ route('pharmacy.receipts.create') }}"><x-primary-button><x-lucide-plus class="h-4 w-4" /> Receive Stock</x-primary-button></a></div>
    <x-card>
        <div class="overflow-x-auto"><table class="min-w-full text-sm"><thead><tr class="text-left text-xs uppercase text-slate-500"><th class="py-3">Receipt</th><th>Supplier</th><th>Location</th><th>Received</th><th>Status</th><th class="text-right">Actions</th></tr></thead><tbody>
            @forelse ($rows as $row)
                <tr class="border-t border-slate-100 dark:border-slate-800"><td class="py-3 font-semibold">{{ $row->receipt_number }}</td><td>{{ $row->supplier?->name }}</td><td>{{ $row->location?->name }}</td><td>{{ $row->received_at?->format('d M Y H:i') }}</td><td><x-badge tone="green">{{ $row->status?->value ?? $row->status }}</x-badge></td><td class="text-right"><a href="{{ route('pharmacy.receipts.show', $row) }}" class="rounded-md p-2 hover:bg-slate-100 dark:hover:bg-slate-800"><x-lucide-eye class="h-4 w-4" /></a></td></tr>
            @empty
                <tr><td colspan="6" class="py-8 text-center text-slate-500">Hakuna receipts.</td></tr>
            @endforelse
        </tbody></table></div>
        <div class="mt-4">{{ $rows->links() }}</div>
    </x-card>
</div>
