<div class="space-y-6">
    <div class="flex items-center justify-between gap-3">
        <a href="{{ route('settings.roles.index') }}" class="inline-flex items-center gap-2 rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-100 dark:hover:bg-slate-800">
            <x-lucide-arrow-left class="h-4 w-4" /> Roles
        </a>
        <x-primary-button wire:click="save"><x-lucide-save class="h-4 w-4" /> Hifadhi Permissions</x-primary-button>
    </div>

    <x-card>
        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="font-semibold">{{ $role->display_name ?? $role->name }}</h3>
                <p class="text-sm text-slate-500">{{ count($selectedPermissions) }} permissions selected</p>
            </div>
            <x-badge :tone="$role->is_active ? 'success' : 'danger'">{{ $role->is_active ? 'Active' : 'Inactive' }}</x-badge>
        </div>
    </x-card>

    <div class="grid gap-4 xl:grid-cols-2">
        @foreach ($permissions as $module => $modulePermissions)
            @php($label = $configuredGroups->get($module)['label'] ?? str($module ?: 'other')->replace('-', ' ')->title())
            <x-card wire:key="permission-module-{{ $module }}">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div>
                        <h3 class="font-semibold">{{ $label }}</h3>
                        <p class="text-xs text-slate-500">{{ $modulePermissions->count() }} permissions</p>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" wire:click="selectModule('{{ $module }}')" class="rounded-md p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800" title="Select all"><x-lucide-check-check class="h-4 w-4" /></button>
                        <button type="button" wire:click="clearModule('{{ $module }}')" class="rounded-md p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800" title="Clear"><x-lucide-eraser class="h-4 w-4" /></button>
                    </div>
                </div>
                <div class="space-y-2">
                    @foreach ($modulePermissions as $permission)
                        <label class="flex items-start gap-3 rounded-md border border-slate-200 p-3 text-sm dark:border-slate-700">
                            <x-checkbox wire:model="selectedPermissions" value="{{ $permission->name }}" class="mt-1" />
                            <span>
                                <span class="block font-medium">{{ $permission->label ?? $permission->name }}</span>
                                <span class="text-xs text-slate-500">{{ $permission->name }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </x-card>
        @endforeach
    </div>
</div>
