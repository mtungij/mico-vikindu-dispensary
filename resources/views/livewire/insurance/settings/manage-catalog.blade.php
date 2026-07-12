<div class="space-y-4">
    <x-card>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold">{{ $title }}</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">Search, filter and manage insurance setup records for the current facility.</p>
            </div>
            <button type="button" wire:click="create" class="inline-flex items-center justify-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white">
                <x-lucide-plus class="h-4 w-4" /> New
            </button>
        </div>
        <div class="mt-4">
            <input wire:model.live.debounce.300ms="search" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900" placeholder="Tafuta kwa jina au code">
        </div>
    </x-card>

    <x-card>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-700">
                <thead>
                    <tr class="text-left text-xs uppercase text-slate-500">
                        <th class="px-3 py-2">Name</th>
                        <th class="px-3 py-2">Code</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($rows as $row)
                        <tr>
                            <td class="px-3 py-2 font-medium">{{ $row->name ?? $row->payer_service_name ?? $row->payer_medicine_name ?? $row->payment_reference ?? '-' }}</td>
                            <td class="px-3 py-2">{{ $row->code ?? $row->payer_service_code ?? $row->payer_medicine_code ?? '-' }}</td>
                            <td class="px-3 py-2">
                                <span class="rounded-full px-2 py-1 text-xs {{ ($row->is_active ?? true) ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-200' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' }}">{{ ($row->is_active ?? true) ? 'Active' : 'Inactive' }}</span>
                            </td>
                            <td class="px-3 py-2">
                                <div class="flex justify-end gap-2">
                                    <button type="button" wire:click="edit({{ $row->id }})" class="rounded-md p-2 hover:bg-slate-100 dark:hover:bg-slate-800" title="Edit"><x-lucide-pencil class="h-4 w-4" /></button>
                                    @if(array_key_exists('is_active', $row->getAttributes()))
                                        <button type="button" wire:click="toggle({{ $row->id }})" class="rounded-md p-2 hover:bg-slate-100 dark:hover:bg-slate-800" title="Activate or deactivate"><x-lucide-power class="h-4 w-4" /></button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-3 py-8 text-center text-slate-500">Hakuna taarifa zilizopatikana.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $rows->links() }}</div>
    </x-card>

    @if($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4">
            <div class="max-h-[90vh] w-full max-w-4xl overflow-y-auto rounded-md bg-white p-5 shadow-xl dark:bg-card-dark">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-lg font-semibold">{{ $editingId ? 'Edit' : 'Create' }} {{ $title }}</h3>
                    <button type="button" wire:click="$set('showModal', false)" class="rounded-md p-2 hover:bg-slate-100 dark:hover:bg-slate-800"><x-lucide-x class="h-5 w-5" /></button>
                </div>
                <form wire:submit="save" class="grid gap-4 md:grid-cols-2">
                    @foreach($fields as $field => $meta)
                        <div class="{{ ($meta['type'] ?? 'text') === 'textarea' ? 'md:col-span-2' : '' }}">
                            <label class="mb-1 block text-sm font-medium">{{ str($field)->replace('_', ' ')->title() }}</label>
                            @if(($meta['type'] ?? 'text') === 'textarea')
                                <textarea wire:model="form.{{ $field }}" rows="3" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"></textarea>
                            @elseif(($meta['type'] ?? 'text') === 'checkbox')
                                <label class="flex items-center gap-2 rounded-md border border-slate-200 px-3 py-2 dark:border-slate-700">
                                    <input type="checkbox" wire:model="form.{{ $field }}" class="rounded border-slate-300">
                                    <span class="text-sm">Enabled</span>
                                </label>
                            @elseif(($meta['type'] ?? 'text') === 'select')
                                <select wire:model="form.{{ $field }}" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900">
                                    @foreach(($meta['options'] ?? []) as $option)
                                        <option value="{{ $option }}">{{ $option === '' ? '-' : str($option)->replace('_', ' ')->title() }}</option>
                                    @endforeach
                                </select>
                            @elseif(($meta['type'] ?? 'text') === 'provider')
                                <select wire:model="form.{{ $field }}" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"><option value="">-</option>@foreach($providers as $provider)<option value="{{ $provider->id }}">{{ $provider->name }}</option>@endforeach</select>
                            @elseif(($meta['type'] ?? 'text') === 'scheme')
                                <select wire:model="form.{{ $field }}" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"><option value="">-</option>@foreach($schemes as $scheme)<option value="{{ $scheme->id }}">{{ $scheme->name }}</option>@endforeach</select>
                            @elseif(($meta['type'] ?? 'text') === 'package')
                                <select wire:model="form.{{ $field }}" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"><option value="">-</option>@foreach($packages as $package)<option value="{{ $package->id }}">{{ $package->name }}</option>@endforeach</select>
                            @elseif(($meta['type'] ?? 'text') === 'service')
                                <select wire:model="form.{{ $field }}" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"><option value="">-</option>@foreach($services as $service)<option value="{{ $service->id }}">{{ $service->name }}</option>@endforeach</select>
                            @else
                                <input type="{{ $meta['type'] ?? 'text' }}" wire:model="form.{{ $field }}" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900">
                            @endif
                            @error('form.'.$field)<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                    <div class="md:col-span-2 flex justify-end gap-2 border-t border-slate-200 pt-4 dark:border-slate-700">
                        <button type="button" wire:click="$set('showModal', false)" class="rounded-md border border-slate-300 px-4 py-2 text-sm dark:border-slate-700">Cancel</button>
                        <button type="submit" class="rounded-md bg-primary px-4 py-2 text-sm font-semibold text-white">Save</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
