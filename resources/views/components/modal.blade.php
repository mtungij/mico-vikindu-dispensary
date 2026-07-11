@props(['show' => false, 'title' => null, 'subtitle' => null, 'maxWidth' => '2xl', 'close' => null])
@php($width = ['sm' => 'max-w-sm', 'md' => 'max-w-md', 'lg' => 'max-w-lg', 'xl' => 'max-w-xl', '2xl' => 'max-w-2xl', '4xl' => 'max-w-4xl', '6xl' => 'max-w-6xl'][$maxWidth] ?? 'max-w-2xl')
@if ($show)
    <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" x-data x-init="document.body.style.overflow='hidden'" x-effect="document.body.style.overflow='hidden'">
        <div class="flex min-h-full items-end justify-center px-4 py-6 sm:items-center">
            <div class="fixed inset-0 bg-slate-950/60" @if($close) wire:click="{{ $close }}" @endif></div>
            <div class="relative w-full {{ $width }} rounded-lg border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-card-dark">
                <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4 dark:border-slate-700">
                    <div>
                        @if ($title)<h2 class="text-base font-semibold">{{ $title }}</h2>@endif
                        @if ($subtitle)<p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $subtitle }}</p>@endif
                    </div>
                    @if ($close)
                        <button type="button" wire:click="{{ $close }}" class="rounded-md p-2 hover:bg-slate-100 dark:hover:bg-slate-800"><x-lucide-x class="h-5 w-5" /></button>
                    @endif
                </div>
                <div class="px-5 py-5">{{ $slot }}</div>
                @isset($footer)
                    <div class="flex justify-end gap-2 border-t border-slate-200 px-5 py-4 dark:border-slate-700">{{ $footer }}</div>
                @endisset
            </div>
        </div>
    </div>
@endif
