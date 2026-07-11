<div class="space-y-3">
    <x-select-input wire:model.live="eventType" class="max-w-xs"><option value="">Matukio yote</option><option value="visit">Visits</option><option value="triage">Triage</option><option value="encounter">Encounters</option></x-select-input>
    <div class="space-y-3">
        @forelse($events as $event)
            <div class="rounded-md border border-slate-200 p-3 dark:border-slate-700"><div class="flex items-center justify-between gap-3"><p class="font-medium">{{ $event['title'] }}</p><span class="text-xs text-slate-500">{{ $event['date']?->format('d/m/Y H:i') }}</span></div><p class="text-sm text-slate-500">{{ $event['type'] }} · {{ $event['summary'] }}</p></div>
        @empty
            <p class="text-sm text-slate-500">Hakuna clinical timeline.</p>
        @endforelse
    </div>
</div>
