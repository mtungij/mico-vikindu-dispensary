@props(['show' => false, 'title' => null, 'close' => null])
@if ($show)
    <div class="fixed inset-0 z-50">
        <div class="absolute inset-0 bg-slate-950/60" @if($close) wire:click="{{ $close }}" @endif></div>
        <aside class="absolute right-0 top-0 h-full w-full max-w-md bg-white shadow-xl dark:bg-card-dark">
            <div class="flex h-16 items-center justify-between border-b border-slate-200 px-5 dark:border-slate-700">
                <h2 class="font-semibold">{{ $title }}</h2>
                @if($close)<button type="button" wire:click="{{ $close }}" class="rounded-md p-2 hover:bg-slate-100 dark:hover:bg-slate-800"><x-lucide-x class="h-5 w-5" /></button>@endif
            </div>
            <div class="p-5">{{ $slot }}</div>
        </aside>
    </div>
@endif
