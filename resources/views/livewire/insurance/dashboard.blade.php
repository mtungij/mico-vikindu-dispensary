<div class="space-y-4">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach($stats as $label => $value)
            <x-card><p class="text-sm text-slate-500">{{ str($label)->replace('_',' ')->title() }}</p><p class="mt-2 text-2xl font-semibold">{{ is_numeric($value) && str_contains($label, 'outstanding') || str_contains($label, 'payments') ? number_format((float)$value, 2) : $value }}</p></x-card>
        @endforeach
    </div>
    <x-card>
        <h3 class="font-semibold">Claims Needing Attention</h3>
        <div class="mt-3 divide-y divide-slate-100 dark:divide-slate-800">
            @forelse($attention as $claim)
                <a href="{{ route('insurance.claims.show', $claim) }}" class="flex items-center justify-between py-3 text-sm">
                    <span>{{ $claim->claim_number }} - {{ $claim->patient?->full_name ?? $claim->patient?->first_name }}</span>
                    <span class="rounded-full bg-amber-50 px-2 py-1 text-xs text-amber-700 dark:bg-amber-950/40 dark:text-amber-200">{{ str($claim->status)->replace('_',' ')->title() }}</span>
                </a>
            @empty
                <p class="py-6 text-sm text-slate-500">Hakuna claim inayohitaji hatua sasa.</p>
            @endforelse
        </div>
    </x-card>
</div>
