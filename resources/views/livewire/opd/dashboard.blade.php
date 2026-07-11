<div class="space-y-6">
    <div class="grid gap-4 md:grid-cols-3 xl:grid-cols-6">
        @foreach($cards as $label => $value)
            <x-card><p class="text-xs text-slate-500">{{ $label }}</p><p class="mt-2 text-2xl font-semibold">{{ $value }}</p></x-card>
        @endforeach
    </div>
    <div class="grid gap-6 xl:grid-cols-2">
        <x-card>
            <div class="mb-4 flex items-center justify-between"><h3 class="font-semibold">My Active Consultations</h3><a href="{{ route('opd.index') }}" class="text-sm text-primary">Foleni</a></div>
            <div class="space-y-3">@forelse($active as $encounter)<a href="{{ route('opd.consultation', $encounter->visit_id) }}" class="block rounded-md border border-slate-200 p-3 dark:border-slate-700"><p class="font-medium">{{ $encounter->patient->fullName() }}</p><p class="text-xs text-slate-500">{{ $encounter->encounter_number }} · {{ $encounter->started_at?->diffForHumans() }}</p></a>@empty<p class="text-sm text-slate-500">Hakuna active consultation.</p>@endforelse</div>
        </x-card>
        <x-card>
            <h3 class="mb-4 font-semibold">Critical Alerts</h3>
            <div class="space-y-3">@forelse($alerts as $alert)<div class="rounded-md border border-red-200 bg-red-50 p-3 text-sm dark:border-red-900 dark:bg-red-950/30"><p class="font-semibold">{{ $alert->title }}</p><p>{{ $alert->patient->fullName() }} · {{ $alert->message }}</p></div>@empty<p class="text-sm text-slate-500">Hakuna critical alerts.</p>@endforelse</div>
        </x-card>
    </div>
</div>
