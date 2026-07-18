<div class="space-y-6">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <div class="grid gap-3 sm:grid-cols-3 lg:min-w-[720px]">
            <x-text-input wire:model.live.debounce.300ms="search" placeholder="Tafuta department..." />
            <x-select-input wire:model.live="status">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="all">Zote</option>
            </x-select-input>
            <x-select-input wire:model.live="type">
                <option value="">Aina zote</option>
                @foreach ($types as $type)
                    <option value="{{ $type->value }}">{{ $type->label() }}</option>
                @endforeach
            </x-select-input>
        </div>
        <div class="flex gap-2">
            <x-secondary-button wire:click="resetFilters"><x-lucide-rotate-ccw class="h-4 w-4" /> Reset</x-secondary-button>
            @can('create', \App\Models\Department::class)
                <x-primary-button wire:click="create"><x-lucide-plus class="h-4 w-4" /> Ongeza</x-primary-button>
            @endcan
        </div>
    </div>

    @error('delete')
        <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-200">{{ $message }}</div>
    @enderror

    <x-card>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                <thead>
                    <tr class="text-left text-xs font-semibold uppercase text-slate-500">
                        <th class="px-4 py-3">Department</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Location</th>
                        <th class="px-4 py-3">Options</th>
                        <th class="px-4 py-3">Linked</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($departments as $department)
                        <tr wire:key="department-{{ $department->id }}">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-9 w-9 items-center justify-center rounded-md text-white" style="background-color: {{ $department->color ?? '#0f766e' }}">
                                        <x-dynamic-component :component="'lucide-'.($department->icon ?: 'building-2')" class="h-4 w-4" />
                                    </span>
                                    <span>
                                        <span class="block font-semibold">{{ $department->name }}</span>
                                        <span class="text-xs text-slate-500">{{ $department->code }}</span>
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3">{{ $department->department_type?->label() ?? '-' }}</td>
                            <td class="px-4 py-3">{{ $department->location ?? '-' }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1">
                                    @if($department->queue_enabled)<x-badge tone="info">Queue</x-badge>@endif
                                    @if($department->billing_enabled)<x-badge tone="warning">Billing</x-badge>@endif
                                    @if($department->clinical_department)<x-badge tone="success">Clinical</x-badge>@endif
                                    @if($department->stock_location_enabled)<x-badge tone="neutral">Stock</x-badge>@endif
                                    <x-badge :tone="$department->is_active ? 'success' : 'danger'">{{ $department->is_active ? 'Active' : 'Inactive' }}</x-badge>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $department->job_titles_count }} vyeo, {{ $department->users_count }} watumishi</td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    @can('activate', $department)
                                        <button type="button" wire:click="toggleStatus({{ $department->id }})" class="rounded-md p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800" title="Badili status"><x-lucide-power class="h-4 w-4" /></button>
                                    @endcan
                                    @can('update', $department)
                                        <button type="button" wire:click="edit({{ $department->id }})" class="rounded-md p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800" title="Hariri"><x-lucide-pencil class="h-4 w-4" /></button>
                                    @endcan
                                    @can('delete', $department)
                                        <button type="button" wire:click="delete({{ $department->id }})" wire:confirm="Una uhakika unataka kufuta department hii?" class="rounded-md p-2 text-danger hover:bg-red-50 dark:hover:bg-red-950/30" title="Futa"><x-lucide-trash-2 class="h-4 w-4" /></button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10"><x-empty-state icon="building-2" title="Hakuna departments" message="Departments zitaonekana hapa baada ya kusajiliwa." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $departments->links() }}</div>
    </x-card>

    <x-modal :show="$showFormModal" :title="$editing ? 'Hariri Department' : 'Ongeza Department'" close="closeFormModal" maxWidth="4xl">
        <form wire:submit="save" class="space-y-4">
            <div class="grid gap-4 md:grid-cols-2">
                <div><x-input-label value="Jina" /><x-text-input wire:model="form.name" /><x-input-error :messages="$errors->get('form.name')" class="mt-1" /></div>
                <div><x-input-label value="Code" /><x-text-input wire:model="form.code" /><x-input-error :messages="$errors->get('form.code')" class="mt-1" /></div>
                <div>
                    <x-input-label value="Aina" />
                    <x-select-input wire:model="form.department_type">
                        <option value="">Chagua</option>
                        @foreach ($types as $type)<option value="{{ $type->value }}">{{ $type->label() }}</option>@endforeach
                    </x-select-input>
                </div>
                <div><x-input-label value="Location" /><x-text-input wire:model="form.location" /></div>
                <div><x-input-label value="Icon (lucide)" /><x-text-input wire:model="form.icon" placeholder="building-2" /></div>
                <div><x-input-label value="Color" /><x-text-input type="color" wire:model="form.color" /></div>
                <div><x-input-label value="Extension" /><x-text-input wire:model="form.phone_extension" /></div>
                <div><x-input-label value="Sort Order" /><x-text-input type="number" min="0" wire:model="form.sort_order" /></div>
            </div>
            <div><x-input-label value="Maelezo" /><x-textarea wire:model="form.description" rows="3" /></div>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <label class="flex items-center gap-2 text-sm"><x-checkbox wire:model="form.queue_enabled" /> Queue</label>
                <label class="flex items-center gap-2 text-sm"><x-checkbox wire:model="form.billing_enabled" /> Billing</label>
                <label class="flex items-center gap-2 text-sm"><x-checkbox wire:model="form.clinical_department" /> Clinical</label>
                <label class="flex items-center gap-2 text-sm"><x-checkbox wire:model="form.stock_location_enabled" /> Stock</label>
                <label class="flex items-center gap-2 text-sm"><x-checkbox wire:model="form.is_active" /> Active</label>
            </div>
            <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-700">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Workflow Configuration</h3>
                <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <label class="flex items-center gap-2 text-sm"><x-checkbox wire:model="form.queue_enabled" /> Queue Enabled</label>
                    <label class="flex items-center gap-2 text-sm"><x-checkbox wire:model="form.can_receive_patients" /> Can Receive Patients</label>
                    <label class="flex items-center gap-2 text-sm"><x-checkbox wire:model="form.requires_consultation" /> Requires Consultation</label>
                    <label class="flex items-center gap-2 text-sm"><x-checkbox wire:model="form.requires_triage" /> Requires Triage</label>
                    <div class="flex items-center justify-between rounded-md bg-slate-50 px-3 py-2 text-sm dark:bg-slate-800">
                        <span>Payment Before Consultation</span>
                        <x-badge :tone="$workflowFlags['payment_before_consultation'] ? 'success' : 'warning'">{{ $workflowFlags['payment_before_consultation'] ? 'Enabled' : 'Disabled' }}</x-badge>
                    </div>
                    <div class="flex items-center justify-between rounded-md bg-slate-50 px-3 py-2 text-sm dark:bg-slate-800">
                        <span>Auto Queue After Payment</span>
                        <x-badge :tone="$workflowFlags['auto_queue_after_payment'] ? 'success' : 'warning'">{{ $workflowFlags['auto_queue_after_payment'] ? 'Enabled' : 'Disabled' }}</x-badge>
                    </div>
                    <div class="flex items-center justify-between rounded-md bg-slate-50 px-3 py-2 text-sm dark:bg-slate-800">
                        <span>Allow Emergency Bypass</span>
                        <x-badge :tone="$workflowFlags['allow_emergency_bypass'] ? 'success' : 'warning'">{{ $workflowFlags['allow_emergency_bypass'] ? 'Enabled' : 'Disabled' }}</x-badge>
                    </div>
                </div>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <x-secondary-button wire:click="closeFormModal">Ghairi</x-secondary-button>
                <x-primary-button><x-lucide-save class="h-4 w-4" /> Hifadhi</x-primary-button>
            </div>
        </form>
    </x-modal>
</div>
