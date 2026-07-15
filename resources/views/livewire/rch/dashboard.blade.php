<div class="space-y-5">
    <div class="grid gap-4 md:grid-cols-3 xl:grid-cols-4">
        @foreach($cards as $label => $value)
            <x-card><p class="text-sm text-slate-500 dark:text-slate-400">{{ $label }}</p><p class="mt-2 text-2xl font-semibold">{{ is_numeric($value) ? number_format($value) : $value }}</p></x-card>
        @endforeach
    </div>
    <div class="grid gap-4 lg:grid-cols-2">
        <x-card><div class="flex items-center justify-between"><h3 class="font-semibold">Quick Actions</h3></div><div class="mt-4 grid gap-2 sm:grid-cols-2"><a class="rounded-md bg-primary px-3 py-2 text-sm text-white" href="{{ route('rch.pregnancies.register') }}">Register Pregnancy</a><a class="rounded-md bg-primary px-3 py-2 text-sm text-white" href="{{ route('rch.children.register') }}">Register Child</a><a class="rounded-md border px-3 py-2 text-sm dark:border-slate-700" href="{{ route('rch.family-planning.index') }}">Family Planning</a><a class="rounded-md border px-3 py-2 text-sm dark:border-slate-700" href="{{ route('rch.immunization.index') }}">Immunization</a></div></x-card>
        <x-card><h3 class="font-semibold">High-risk Pregnancies</h3><div class="mt-3 space-y-2">@forelse($highRisk as $pregnancy)<a class="block rounded-md border p-3 text-sm dark:border-slate-700" href="{{ route('rch.pregnancies.show',$pregnancy) }}">{{ $pregnancy->patient->fullName() }} · {{ $pregnancy->risk_level }}</a>@empty<x-empty-state icon="shield-check" title="No high-risk pregnancies" />@endforelse</div></x-card>
    </div>
</div>
