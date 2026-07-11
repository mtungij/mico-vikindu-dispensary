@props(['label', 'model', 'description' => null])
<label class="flex items-start justify-between gap-4 rounded-md border border-slate-200 p-3 dark:border-slate-700">
    <span>
        <span class="block text-sm font-medium">{{ $label }}</span>
        @if ($description)<span class="mt-1 block text-xs text-slate-500 dark:text-slate-400">{{ $description }}</span>@endif
    </span>
    <input type="checkbox" wire:model.live="{{ $model }}" class="mt-1 rounded border-slate-300 text-primary focus:ring-primary">
</label>
