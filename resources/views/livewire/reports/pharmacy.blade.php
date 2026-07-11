<div class="space-y-6">
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('reports.pharmacy', 'stock-movement') }}" class="rounded-md px-3 py-2 text-sm {{ $type === 'stock-movement' ? 'bg-primary text-white' : 'bg-white dark:bg-card-dark' }}">Stock Movement</a>
            <a href="{{ route('reports.pharmacy', 'expiry-report') }}" class="rounded-md px-3 py-2 text-sm {{ $type === 'expiry-report' ? 'bg-primary text-white' : 'bg-white dark:bg-card-dark' }}">Expiry Report</a>
        </div>
        @can('pharmacy.reports.export')
            <a href="{{ route('reports.pharmacy.export', $type) }}"><x-primary-button><x-lucide-download class="h-4 w-4" /> Export CSV</x-primary-button></a>
        @endcan
    </div>

    <x-card>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                @if ($type === 'expiry-report')
                    <thead><tr class="text-left text-xs uppercase text-slate-500"><th class="py-3">Medicine</th><th>Batch</th><th>Location</th><th>Expiry</th><th>Available</th></tr></thead>
                    <tbody>@foreach($rows as $batch)<tr class="border-t border-slate-100 dark:border-slate-800"><td class="py-3 font-semibold">{{ $batch->medicine?->name }}</td><td>{{ $batch->batch_number }}</td><td>{{ $batch->location?->name }}</td><td>{{ $batch->expiry_date?->format('d M Y') }}</td><td>{{ $batch->available_quantity }}</td></tr>@endforeach</tbody>
                @else
                    <thead><tr class="text-left text-xs uppercase text-slate-500"><th class="py-3">Date</th><th>Medicine</th><th>Batch</th><th>Location</th><th>Type</th><th>Qty</th></tr></thead>
                    <tbody>@foreach($rows as $movement)<tr class="border-t border-slate-100 dark:border-slate-800"><td class="py-3">{{ $movement->occurred_at?->format('d M Y H:i') }}</td><td class="font-semibold">{{ $movement->medicine?->name }}</td><td>{{ $movement->batch?->batch_number }}</td><td>{{ $movement->location?->name }}</td><td>{{ $movement->movement_type?->value ?? $movement->movement_type }}</td><td>{{ $movement->quantity }}</td></tr>@endforeach</tbody>
                @endif
            </table>
        </div>
        <div class="mt-4">{{ $rows->links() }}</div>
    </x-card>
</div>
