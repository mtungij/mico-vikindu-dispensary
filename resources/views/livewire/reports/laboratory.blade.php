<div class="space-y-4">
    <div class="flex flex-wrap items-center gap-2">
        @foreach(['orders' => 'Orders', 'tests' => 'Tests', 'samples' => 'Samples', 'results' => 'Results', 'critical-results' => 'Critical', 'revenue' => 'Revenue', 'turnaround-time' => 'TAT'] as $key => $label)
            <a href="{{ route('reports.laboratory', $key) }}" class="rounded-md px-3 py-2 text-sm {{ $type === $key ? 'bg-primary text-white' : 'bg-white text-slate-700 ring-1 ring-slate-200 dark:bg-card-dark dark:text-slate-200 dark:ring-slate-700' }}">{{ $label }}</a>
        @endforeach
        <a href="{{ route('reports.laboratory.export', $type) }}" class="ml-auto rounded-md bg-primary px-3 py-2 text-sm text-white">CSV</a>
    </div>

    <div class="grid gap-3 sm:grid-cols-4">
        @foreach($summary as $label => $value)
            <x-card><p class="text-xs text-slate-500">{{ str($label)->replace('_', ' ')->title() }}</p><p class="mt-1 text-2xl font-semibold">{{ $value ?? '-' }}</p></x-card>
        @endforeach
    </div>

    <x-card>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="text-left text-xs uppercase text-slate-500">
                        <th class="py-3">Date</th><th>Reference</th><th>Patient/Test</th><th>Status</th><th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr class="border-t border-slate-100 dark:border-slate-800">
                            <td class="py-3">{{ ($row->ordered_at ?? $row->collected_at ?? $row->entered_at ?? $row->notified_at ?? $row->created_at)?->format('d/m/Y H:i') }}</td>
                            <td>{{ $row->order_number ?? $row->sample_number ?? $row->code ?? $row->result?->order?->order_number ?? $row->order?->order_number ?? '-' }}</td>
                            <td>{{ $row->patient?->fullName() ?? $row->order?->patient?->fullName() ?? $row->result?->order?->patient?->fullName() ?? $row->name ?? $row->test?->name ?? $row->result?->test?->name ?? '-' }}</td>
                            <td>{{ $row->status?->value ?? $row->sample_status?->value ?? $row->result_status?->value ?? ($row->is_active ?? null ? 'active' : '-') }}</td>
                            <td>{{ $row->items_count ?? $row->specimenType?->name ?? $row->category?->name ?? $row->abnormal_flag?->value ?? $row->notification_method ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-6 text-center text-slate-500">Hakuna taarifa.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</div>
