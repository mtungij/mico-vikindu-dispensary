@props(['label', 'model'])
<div>
    <x-input-label :value="$label" />
    <div class="mt-1 flex gap-2">
        <input type="color" wire:model.live="{{ $model }}" class="h-10 w-12 rounded-md border border-slate-300 bg-white p-1 dark:border-slate-700 dark:bg-slate-900">
        <x-text-input wire:model.live="{{ $model }}" class="font-mono" />
    </div>
    <x-input-error :messages="$errors->get($model)" />
</div>
