<div class="space-y-6">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <div class="grid gap-3 sm:grid-cols-2 lg:min-w-[520px]">
            <x-text-input wire:model.live.debounce.300ms="search" placeholder="Tafuta permission..." />
            <x-select-input wire:model.live="module">
                <option value="">Modules zote</option>
                @foreach ($modules as $item)<option value="{{ $item }}">{{ $item }}</option>@endforeach
            </x-select-input>
        </div>
        <x-primary-button wire:click="sync"><x-lucide-refresh-cw class="h-4 w-4" /> Sync</x-primary-button>
    </div>

    <div class="grid gap-4 xl:grid-cols-2">
        @forelse ($permissions as $module => $modulePermissions)
            <x-card wire:key="permissions-{{ $module }}">
                <div class="mb-4">
                    <h3 class="font-semibold">{{ config("permissions.$module.label") ?? str($module ?: 'other')->replace('-', ' ')->title() }}</h3>
                    <p class="text-xs text-slate-500">{{ $modulePermissions->count() }} permissions</p>
                </div>
                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($modulePermissions as $permission)
                        <div class="py-3">
                            <p class="font-medium">{{ $permission->label ?? $permission->name }}</p>
                            <p class="text-xs text-slate-500">{{ $permission->name }}</p>
                        </div>
                    @endforeach
                </div>
            </x-card>
        @empty
            <x-card class="xl:col-span-2">
                <x-empty-state icon="shield" title="Hakuna permissions" message="Bonyeza Sync kuunda permissions kutoka config." />
            </x-card>
        @endforelse
    </div>
</div>
