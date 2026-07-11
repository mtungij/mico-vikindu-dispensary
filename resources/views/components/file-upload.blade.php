@props(['label', 'model', 'accept' => null, 'hint' => null])
<div class="rounded-lg border border-dashed border-slate-300 p-4 dark:border-slate-700">
    <div class="flex items-start gap-3">
        <div class="rounded-md bg-slate-100 p-2 text-primary dark:bg-slate-800"><x-lucide-upload class="h-5 w-5" /></div>
        <div class="min-w-0 flex-1">
            <x-input-label :value="$label" />
            @if ($hint)<p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $hint }}</p>@endif
            <input type="file" wire:model="{{ $model }}" @if($accept) accept="{{ $accept }}" @endif class="mt-3 block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-primary file:px-3 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-teal-800">
            <div wire:loading wire:target="{{ $model }}" class="mt-2 flex items-center gap-2 text-sm text-slate-500"><x-loading-spinner /> Inapakia...</div>
            <x-input-error :messages="$errors->get($model)" />
        </div>
    </div>
</div>
