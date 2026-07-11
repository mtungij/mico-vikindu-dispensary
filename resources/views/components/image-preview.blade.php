@props(['path' => null, 'label' => 'Preview'])
<div class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
    <p class="mb-2 text-xs font-medium text-slate-500 dark:text-slate-400">{{ $label }}</p>
    @if ($path)
        <img src="{{ asset('storage/'.$path) }}" alt="{{ $label }}" class="h-24 max-w-full rounded-md object-contain">
    @else
        <div class="flex h-24 items-center justify-center rounded-md bg-slate-100 text-slate-400 dark:bg-slate-800"><x-lucide-image class="h-6 w-6" /></div>
    @endif
</div>
