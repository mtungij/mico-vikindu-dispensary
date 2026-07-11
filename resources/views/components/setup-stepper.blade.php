@props(['steps', 'current', 'progress' => 0])
<div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-card-dark">
    <div class="mb-4 flex items-center justify-between gap-4">
        <p class="text-sm font-semibold">Progress</p>
        <p class="text-sm text-slate-500 dark:text-slate-400">{{ $progress }}%</p>
    </div>
    <div class="h-2 rounded bg-slate-100 dark:bg-slate-800">
        <div class="h-2 rounded bg-primary transition-all" style="width: {{ $progress }}%"></div>
    </div>
    <div class="mt-5 grid gap-2 md:grid-cols-6">
        @foreach ($steps as $number => $label)
            <button type="button" wire:click="goToStep({{ $number }})" class="flex items-center gap-2 rounded-md border px-3 py-2 text-left text-sm transition {{ $current === $number ? 'border-primary bg-primary text-white' : ($current > $number ? 'border-green-200 bg-green-50 text-success dark:border-green-900 dark:bg-green-950/30' : 'border-slate-200 text-slate-500 dark:border-slate-700 dark:text-slate-400') }}">
                @if ($current > $number)
                    <x-lucide-check-circle-2 class="h-4 w-4 shrink-0" />
                @else
                    <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full border text-xs">{{ $number }}</span>
                @endif
                <span class="hidden truncate lg:block">{{ $label }}</span>
            </button>
        @endforeach
    </div>
</div>
