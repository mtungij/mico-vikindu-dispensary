<div class="space-y-6">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        @foreach($stats as $stat)
            <x-card><div class="flex items-center gap-3"><x-dynamic-component :component="'lucide-'.$stat['icon']" class="h-5 w-5 text-primary" /><div><p class="text-xs text-slate-500">{{ $stat['label'] }}</p><p class="text-2xl font-semibold">{{ $stat['value'] }}</p></div></div></x-card>
        @endforeach
    </div>
    <div class="grid gap-6 xl:grid-cols-3">
        <x-card><h3 class="mb-3 font-semibold">Recent Dispensing</h3>@forelse($recentDispensings as $row)<div class="border-t border-slate-100 py-2 text-sm dark:border-slate-800">{{ $row->dispensing_number }} · {{ $row->patient?->fullName() }} · {{ $row->status->value }}</div>@empty<p class="text-sm text-slate-500">Hakuna dispensing.</p>@endforelse</x-card>
        <x-card><h3 class="mb-3 font-semibold">Low Stock</h3>@forelse($lowStock as $medicine)<div class="border-t border-slate-100 py-2 text-sm dark:border-slate-800">{{ $medicine->name }} · {{ $medicine->currentStock() }}</div>@empty<p class="text-sm text-slate-500">Hakuna low stock.</p>@endforelse</x-card>
        <x-card><h3 class="mb-3 font-semibold">Expiry Alerts</h3>@forelse($expiring as $batch)<div class="border-t border-slate-100 py-2 text-sm dark:border-slate-800">{{ $batch->medicine?->name }} · {{ $batch->batch_number }} · {{ $batch->expiry_date?->format('d/m/Y') }}</div>@empty<p class="text-sm text-slate-500">Hakuna expiry alerts.</p>@endforelse</x-card>
    </div>
</div>
