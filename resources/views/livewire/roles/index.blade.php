<div class="space-y-6">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <div class="grid gap-3 sm:grid-cols-2 lg:min-w-[520px]">
            <x-text-input wire:model.live.debounce.300ms="search" placeholder="Tafuta role..." />
            <x-select-input wire:model.live="status">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="all">Zote</option>
            </x-select-input>
        </div>
        <div class="flex gap-2">
            <x-secondary-button wire:click="resetFilters"><x-lucide-rotate-ccw class="h-4 w-4" /> Reset</x-secondary-button>
            @can('create', \App\Models\Role::class)
                <x-primary-button wire:click="create"><x-lucide-plus class="h-4 w-4" /> Ongeza</x-primary-button>
            @endcan
        </div>
    </div>

    <x-card>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                <thead>
                    <tr class="text-left text-xs font-semibold uppercase text-slate-500">
                        <th class="px-4 py-3">Role</th>
                        <th class="px-4 py-3">Scope</th>
                        <th class="px-4 py-3">Permissions</th>
                        <th class="px-4 py-3">Users</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($roles as $role)
                        <tr wire:key="role-{{ $role->id }}">
                            <td class="px-4 py-3">
                                <span class="block font-semibold">{{ $role->display_name ?? $role->name }}</span>
                                <span class="text-xs text-slate-500">{{ $role->name }}</span>
                            </td>
                            <td class="px-4 py-3">{{ $role->facility_id ? 'Facility' : 'Global' }}</td>
                            <td class="px-4 py-3">{{ $role->permissions_count }}</td>
                            <td class="px-4 py-3">{{ $role->users_count }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1">
                                    <x-badge :tone="$role->is_active ? 'success' : 'danger'">{{ $role->is_active ? 'Active' : 'Inactive' }}</x-badge>
                                    @if($role->is_system)<x-badge tone="info">System</x-badge>@endif
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    @can('assignPermissions', $role)
                                        <a href="{{ route('settings.roles.permissions', $role) }}" class="rounded-md p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800" title="Permissions"><x-lucide-shield-check class="h-4 w-4" /></a>
                                    @endcan
                                    @can('update', $role)
                                        <button type="button" wire:click="toggleStatus({{ $role->id }})" class="rounded-md p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800" title="Badili status"><x-lucide-power class="h-4 w-4" /></button>
                                        <button type="button" wire:click="edit({{ $role->id }})" class="rounded-md p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800" title="Hariri"><x-lucide-pencil class="h-4 w-4" /></button>
                                    @endcan
                                    @can('delete', $role)
                                        <button type="button" wire:click="delete({{ $role->id }})" wire:confirm="Una uhakika unataka kufuta role hii?" class="rounded-md p-2 text-danger hover:bg-red-50 dark:hover:bg-red-950/30" title="Futa"><x-lucide-trash-2 class="h-4 w-4" /></button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10"><x-empty-state icon="shield-check" title="Hakuna roles" message="Roles zitaonekana hapa baada ya kusajiliwa." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $roles->links() }}</div>
    </x-card>

    <x-modal :show="$showFormModal" :title="$editing ? 'Hariri Role' : 'Ongeza Role'" close="closeFormModal" maxWidth="2xl">
        <form wire:submit="save" class="space-y-4">
            <div class="grid gap-4 md:grid-cols-2">
                <div><x-input-label value="Display Name" /><x-text-input wire:model="form.display_name" /><x-input-error :messages="$errors->get('form.display_name')" class="mt-1" /></div>
                <div><x-input-label value="Name" /><x-text-input wire:model="form.name" /><x-input-error :messages="$errors->get('form.name')" class="mt-1" /></div>
            </div>
            <div><x-input-label value="Maelezo" /><x-textarea wire:model="form.description" rows="3" /></div>
            @unless($editing)
                <div>
                    <x-input-label value="Copy permissions from" />
                    <x-select-input wire:model="form.copy_from_role_id">
                        <option value="">Usicopy</option>
                        @foreach ($copyRoles as $copyRole)<option value="{{ $copyRole->id }}">{{ $copyRole->display_name ?? $copyRole->name }}</option>@endforeach
                    </x-select-input>
                </div>
            @endunless
            <label class="flex items-center gap-2 text-sm"><x-checkbox wire:model="form.is_active" /> Active</label>
            <div class="flex justify-end gap-2 pt-2">
                <x-secondary-button wire:click="closeFormModal">Ghairi</x-secondary-button>
                <x-primary-button><x-lucide-save class="h-4 w-4" /> Hifadhi</x-primary-button>
            </div>
        </form>
    </x-modal>
</div>
