@props(['icon' => 'search', 'title' => 'Hakuna taarifa', 'message' => 'Taarifa zitaonekana hapa zitakapopatikana.'])
<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center rounded-lg border border-dashed border-slate-300 p-8 text-center dark:border-slate-700']) }}>
    <div class="mb-3 rounded-md bg-slate-100 p-3 text-slate-500 dark:bg-slate-800 dark:text-slate-300">
        <x-dynamic-component :component="'lucide-'.$icon" class="h-6 w-6" />
    </div>
    <p class="text-sm font-semibold">{{ $title }}</p>
    <p class="mt-1 max-w-sm text-sm text-slate-500 dark:text-slate-400">{{ $message }}</p>
</div>
